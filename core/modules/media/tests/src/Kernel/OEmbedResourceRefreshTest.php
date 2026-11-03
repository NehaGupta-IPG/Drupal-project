<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\OEmbed\ResourceFetcher;
use Drupal\media\Plugin\QueueWorker\OEmbedResourceRefresher;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests refreshing stored oEmbed resources through the queue.
 */
#[CoversClass(ResourceFetcher::class)]
#[CoversClass(OEmbedResourceRefresher::class)]
#[Group('media')]
#[RunTestsInSeparateProcesses]
class OEmbedResourceRefreshTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'image',
    'media',
    'system',
    'user',
  ];

  /**
   * Tests that stored resources survive cache clears and are refreshed.
   */
  public function testRefresh(): void {
    $url = 'https://example.com/oembed?url=resource';
    $this->container->set('http_client', new Client([
      'handler' => HandlerStack::create(new MockHandler([
        $this->createVideoResponse('old'),
        $this->createVideoResponse('new'),
      ])),
    ]));
    // Use a time service that the test can move forward.
    $time = new class($this->container->get('datetime.time')) implements TimeInterface {

      /**
       * The number of seconds to add to the current time.
       */
      public int $offset = 0;

      public function __construct(protected TimeInterface $time) {}

      /**
       * {@inheritdoc}
       */
      public function getRequestTime(): int {
        return $this->time->getRequestTime() + $this->offset;
      }

      /**
       * {@inheritdoc}
       */
      public function getRequestMicroTime(): float {
        return $this->time->getRequestMicroTime() + $this->offset;
      }

      /**
       * {@inheritdoc}
       */
      public function getCurrentTime(): int {
        return $this->time->getCurrentTime() + $this->offset;
      }

      /**
       * {@inheritdoc}
       */
      public function getCurrentMicroTime(): float {
        return $this->time->getCurrentMicroTime() + $this->offset;
      }

    };
    $this->container->set('datetime.time', $time);
    $fetcher = $this->container->get('media.oembed.resource_fetcher');
    $this->assertInstanceOf(ResourceFetcher::class, $fetcher);
    $queue = $this->container->get('queue')->get(ResourceFetcher::QUEUE_NAME);

    $this->assertSame('old', $fetcher->fetchResource($url)->getHtml());

    // Cache clears do not remove stored resources.
    foreach ($this->container->getParameter('cache_bins') as $service_id => $bin) {
      $this->container->get($service_id)->deleteAll();
    }
    $this->assertSame('old', $fetcher->fetchResource($url)->getHtml());
    $this->assertSame(0, $queue->numberOfItems());

    // Move time forward until the stored resource is due for a refresh.
    $time->offset = ResourceFetcher::REFRESH_AGE;

    // The stored resource is still returned, and one refresh is queued.
    $this->assertSame('old', $fetcher->fetchResource($url)->getHtml());
    $this->assertSame('old', $fetcher->fetchResource($url)->getHtml());
    $this->assertSame(1, $queue->numberOfItems());

    // Processing the queue stores the refreshed resource.
    $this->container->get('cron')->run();
    $this->assertSame(0, $queue->numberOfItems());
    $this->assertSame('new', $fetcher->fetchResource($url)->getHtml());
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
  protected function createVideoResponse(string $html): Response {
    return new Response(200, ['Content-Type' => ['application/json']], Json::encode([
      'version' => '1.0',
      'type' => 'video',
      'html' => $html,
    ]));
  }

}
