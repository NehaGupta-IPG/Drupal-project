<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\NullBackend;
use Drupal\media\OEmbed\ProviderRepositoryInterface;
use Drupal\media\OEmbed\ResourceException;
use Drupal\media\OEmbed\ResourceFetcher;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\media\OEmbed\ResourceFetcher.
 */
#[CoversClass(ResourceFetcher::class)]
#[Group('media')]
class ResourceFetcherTest extends UnitTestCase {

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
    $client = $this->prophesize(Client::class);
    $client->request('GET', $url, [RequestOptions::TIMEOUT => $non_default_timeout])
      ->shouldBeCalled()
      ->willReturn($response);

    $fetcher = new ResourceFetcher(
      $client->reveal(),
      $this->createStub(ProviderRepositoryInterface::class),
      new NullBackend('default'),
      $non_default_timeout
    );
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

    $mock_handler = new MockHandler([
      $valid_response,
      $invalid_response,
      $non_array_response,
    ]);
    $client = new Client([
      'handler' => HandlerStack::create($mock_handler),
    ]);
    $providers = $this->createStub(ProviderRepositoryInterface::class);

    $fetcher = new ResourceFetcher($client, $providers, new NullBackend('default'));
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
   * Tests the exception message when the resource cannot be retrieved.
   *
   * @param \GuzzleHttp\Psr7\Response|\Throwable $mock_result
   *   The response or exception returned by the HTTP client.
   * @param string[] $expected_messages
   *   Strings the ResourceException message must contain.
   */
  #[DataProvider('providerFetchFailure')]
  public function testFetchFailure(Response|\Throwable $mock_result, array $expected_messages): void {
    $client = new Client([
      'handler' => HandlerStack::create(new MockHandler([$mock_result])),
    ]);
    $fetcher = new ResourceFetcher($client, $this->createStub(ProviderRepositoryInterface::class), new NullBackend('default'));

    try {
      $fetcher->fetchResource('https://example.com/oembed?url=resource');
      $this->fail('Expected a ResourceException to be thrown.');
    }
    catch (ResourceException $e) {
      foreach ($expected_messages as $expected_message) {
        $this->assertStringContainsString($expected_message, $e->getMessage());
      }
    }
  }

  /**
   * Data provider for ::testFetchFailure().
   */
  public static function providerFetchFailure(): array {
    return [
      'provider error response' => [
        new Response(400, ['Content-Type' => 'application/json'], Json::encode([
          'error' => ['message' => 'Invalid OAuth access token.'],
        ])),
        [
          'Could not retrieve the oEmbed resource: ',
          '400 Bad Request',
          'Invalid OAuth access token.',
        ],
      ],
      'network error' => [
        new ConnectException('Connection timed out', new Request('GET', 'https://example.com/oembed?url=resource')),
        ['Could not retrieve the oEmbed resource: Connection timed out'],
      ],
    ];
  }

}
