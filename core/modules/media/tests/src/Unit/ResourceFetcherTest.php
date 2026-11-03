<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\NullBackend;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\KeyValueStore\MemoryStorage;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\media\OEmbed\ProviderRepositoryInterface;
use Drupal\media\OEmbed\ResourceException;
use Drupal\media\OEmbed\ResourceFetcher;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * Tests Drupal\media\OEmbed\ResourceFetcher.
 */
#[CoversClass(ResourceFetcher::class)]
#[Group('media')]
class ResourceFetcherTest extends UnitTestCase {

  /**
   * The current time returned by the mocked time service.
   */
  protected int $currentTime = 1000000000;

  /**
   * The expirable key/value store used by the resource fetcher.
   */
  protected KeyValueStoreExpirableInterface $keyValue;

  /**
   * The queue items created by the resource fetcher.
   */
  protected array $queuedItems = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->keyValue = new class('media.oembed_resource') extends MemoryStorage implements KeyValueStoreExpirableInterface {

      /**
       * {@inheritdoc}
       */
      public function setWithExpire($key, $value, $expire): void {
        $this->set($key, $value);
      }

      /**
       * {@inheritdoc}
       */
      public function setWithExpireIfNotExists($key, $value, $expire): bool {
        return $this->setIfNotExists($key, $value);
      }

      /**
       * {@inheritdoc}
       */
      public function setMultipleWithExpire(array $data, $expire): void {
        $this->setMultiple($data);
      }

    };
  }

  /**
   * Creates a resource fetcher with in-memory storage and queue.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   * @param int $timeout
   *   The HTTP request timeout.
   *
   * @return \Drupal\media\OEmbed\ResourceFetcher
   *   The resource fetcher.
   */
  protected function createFetcher(ClientInterface $client, int $timeout = 5): ResourceFetcher {
    $key_value_factory = $this->createStub(KeyValueExpirableFactoryInterface::class);
    $key_value_factory->method('get')->willReturn($this->keyValue);

    $queue = $this->createStub(QueueInterface::class);
    $queue->method('createItem')->willReturnCallback(function ($data) {
      $this->queuedItems[] = $data;
      return count($this->queuedItems);
    });
    $queue_factory = $this->createStub(QueueFactory::class);
    $queue_factory->method('get')->willReturn($queue);

    $time = $this->createStub(TimeInterface::class);
    $time->method('getCurrentTime')->willReturnCallback(fn () => $this->currentTime);

    return new ResourceFetcher(
      $client,
      $this->createStub(ProviderRepositoryInterface::class),
      new NullBackend('default'),
      $timeout,
      $key_value_factory,
      $queue_factory,
      $time,
    );
  }

  /**
   * Creates an HTTP client that returns the given responses in order.
   *
   * @param \GuzzleHttp\Psr7\Response ...$responses
   *   The responses to return.
   *
   * @return \GuzzleHttp\Client
   *   The HTTP client. It throws an exception when called more often than the
   *   number of responses.
   */
  protected function createClient(Response ...$responses): Client {
    return new Client([
      'handler' => HandlerStack::create(new MockHandler($responses)),
    ]);
  }

  /**
   * Creates a valid oEmbed video response.
   *
   * @param string $html
   *   The HTML of the video.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   The response.
   */
  protected function createVideoResponse(string $html = 'test'): Response {
    return new Response(200, ['Content-Type' => ['application/json']], Json::encode([
      'version' => '1.0',
      'type' => 'video',
      'html' => $html,
    ]));
  }

  /**
   * Tests that resources are fetched with a hard-coded timeout.
   */
  public function testFetchTimeout(): void {
    $url = 'https://example.com/oembed?url=resource';
    $headers = [
      'Content-Type' => ['text/javascript'],
    ];
    $body = Json::encode([
      'version' => '1.0',
      'type' => 'video',
      'html' => 'test',
    ]);
    $response = new Response(200, $headers, $body);

    $non_default_timeout = 10;
    $client = $this->createMock(ClientInterface::class);
    $client->expects($this->once())
      ->method('request')
      ->with('GET', $url, [RequestOptions::TIMEOUT => $non_default_timeout])
      ->willReturn($response);

    $fetcher = $this->createFetcher($client, $non_default_timeout);
    $fetcher->fetchResource($url);
  }

  /**
   * Tests how the resource fetcher handles unknown Content-Type headers.
   *
   * @legacy-covers ::fetchResource
   */
  public function testUnknownContentTypeHeader(): void {
    $headers = [
      'Content-Type' => ['text/html'],
    ];
    $body = Json::encode([
      'version' => '1.0',
      'type' => 'video',
      'html' => 'test',
    ]);
    $valid_response = new Response(200, $headers, $body);
    // Strip off the trailing '}' to produce a response that will cause a JSON
    // parse error.
    $invalid_response = new Response(200, $headers, rtrim($body, '}'));
    // A response that is valid JSON, but does not decode to an array, should
    // produce an exception as well.
    $non_array_response = new Response(200, $headers, '"Valid JSON, but not an array..."');

    $fetcher = $this->createFetcher($this->createClient($valid_response, $invalid_response, $non_array_response));
    /** @var \Drupal\media\OEmbed\Resource $resource */
    $resource = $fetcher->fetchResource('valid');
    // The resource should have been successfully decoded as JSON.
    $this->assertSame('video', $resource->getType());
    $this->assertSame('test', $resource->getHtml());

    // Invalid JSON should throw an exception.
    try {
      $fetcher->fetchResource('invalid');
      $this->fail('Expected a ResourceException to be thrown for invalid JSON.');
    }
    catch (ResourceException $e) {
      $this->assertStringStartsWith('Error decoding oEmbed resource: Syntax error', $e->getMessage());
    }

    // Valid JSON that does not produce an array should also throw an exception.
    $this->expectException(ResourceException::class);
    $this->expectExceptionMessageIs('The oEmbed resource could not be decoded.');
    $fetcher->fetchResource('non_array');
  }

  /**
   * Tests that stored resource data is used without an HTTP request.
   */
  public function testStoredResource(): void {
    // The client only has one response, so a second request would fail.
    $fetcher = $this->createFetcher($this->createClient($this->createVideoResponse()));
    $url = 'https://example.com/oembed?url=resource';

    $this->assertSame('test', $fetcher->fetchResource($url)->getHtml());
    $stored = current($this->keyValue->getAll());
    $this->assertSame($this->currentTime + ResourceFetcher::MAX_AGE, $stored['expires']);

    $this->currentTime += ResourceFetcher::REFRESH_AGE - 1;
    $this->assertSame('test', $fetcher->fetchResource($url)->getHtml());
    $this->assertSame([], $this->queuedItems);
  }

  /**
   * Tests that a refresh is queued before stored resource data expires.
   */
  public function testRefreshQueued(): void {
    $fetcher = $this->createFetcher($this->createClient($this->createVideoResponse()));
    $url = 'https://example.com/oembed?url=resource';
    $fetcher->fetchResource($url);
    $expires = current($this->keyValue->getAll())['expires'];

    // Stored data that is due for a refresh is returned, and a refresh is
    // queued.
    $this->currentTime += ResourceFetcher::REFRESH_AGE;
    $this->assertSame('test', $fetcher->fetchResource($url)->getHtml());
    $this->assertSame([['url' => $url]], $this->queuedItems);
    // The stored data keeps its original expiry.
    $stored = current($this->keyValue->getAll());
    $this->assertSame($expires, $stored['expires']);
    $this->assertSame($this->currentTime, $stored['queued']);

    // A refresh is not queued again while the previous one is pending.
    $this->currentTime += ResourceFetcher::REQUEUE_AGE - 1;
    $fetcher->fetchResource($url);
    $this->assertCount(1, $this->queuedItems);

    // A refresh is queued again when the previous one did not run in time.
    $this->currentTime += 1;
    $fetcher->fetchResource($url);
    $this->assertSame([['url' => $url], ['url' => $url]], $this->queuedItems);
  }

  /**
   * Tests refreshing stored resource data.
   */
  public function testRefreshResource(): void {
    $fetcher = $this->createFetcher($this->createClient(
      $this->createVideoResponse('old'),
      $this->createVideoResponse('new'),
    ));
    $url = 'https://example.com/oembed?url=resource';
    $fetcher->fetchResource($url);

    $this->currentTime += ResourceFetcher::REFRESH_AGE;
    $fetcher->fetchResource($url);
    $this->assertCount(1, $this->queuedItems);

    // Refreshing fetches the resource again and stores it as new data.
    $this->assertSame('new', $fetcher->refreshResource($url)['html']);
    $this->assertSame('new', $fetcher->fetchResource($url)->getHtml());
    $stored = current($this->keyValue->getAll());
    $this->assertSame($this->currentTime + ResourceFetcher::REFRESH_AGE, $stored['refresh']);
    $this->assertSame($this->currentTime + ResourceFetcher::MAX_AGE, $stored['expires']);
    $this->assertNull($stored['queued']);
  }

  /**
   * Tests the deprecation of calling the constructor without all arguments.
   */
  #[IgnoreDeprecations]
  public function testConstructorDeprecation(): void {
    $container = new ContainerBuilder();
    $container->set('keyvalue.expirable', $this->createStub(KeyValueExpirableFactoryInterface::class));
    $container->set('queue', $this->createStub(QueueFactory::class));
    $container->set('datetime.time', $this->createStub(TimeInterface::class));
    \Drupal::setContainer($container);

    $this->expectUserDeprecationMessage('Calling Drupal\media\OEmbed\ResourceFetcher::__construct() without the $keyValueExpirableFactory argument is deprecated in drupal:12.1.0 and will be required in drupal:13.0.0. See https://www.drupal.org/node/3634964');
    $this->expectUserDeprecationMessage('Calling Drupal\media\OEmbed\ResourceFetcher::__construct() without the $queueFactory argument is deprecated in drupal:12.1.0 and will be required in drupal:13.0.0. See https://www.drupal.org/node/3634964');
    $this->expectUserDeprecationMessage('Calling Drupal\media\OEmbed\ResourceFetcher::__construct() without the $time argument is deprecated in drupal:12.1.0 and will be required in drupal:13.0.0. See https://www.drupal.org/node/3634964');
    new ResourceFetcher(
      $this->createStub(ClientInterface::class),
      $this->createStub(ProviderRepositoryInterface::class),
      new NullBackend('default'),
    );
  }

}
