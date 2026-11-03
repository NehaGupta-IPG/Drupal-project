<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Unit\Plugin\QueueWorker;

use Drupal\media\OEmbed\ResourceException;
use Drupal\media\OEmbed\ResourceFetcher;
use Drupal\media\OEmbed\ResourceFetcherInterface;
use Drupal\media\Plugin\QueueWorker\OEmbedResourceRefresher;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests Drupal\media\Plugin\QueueWorker\OEmbedResourceRefresher.
 */
#[CoversClass(OEmbedResourceRefresher::class)]
#[Group('media')]
class OEmbedResourceRefresherTest extends UnitTestCase {

  /**
   * Tests that a queue item refreshes the stored resource.
   */
  public function testProcessItem(): void {
    $url = 'https://example.com/oembed?url=resource';
    $fetcher = $this->createMock(ResourceFetcher::class);
    $fetcher->expects($this->once())
      ->method('refreshResource')
      ->with($url)
      ->willReturn(['version' => '1.0']);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->never())->method('warning');

    $worker = new OEmbedResourceRefresher([], ResourceFetcher::QUEUE_NAME, [], $fetcher, $logger);
    $worker->processItem(['url' => $url]);
  }

  /**
   * Tests that a failed refresh is logged and not retried.
   */
  public function testProcessItemFailure(): void {
    $url = 'https://example.com/oembed?url=resource';
    $fetcher = $this->createMock(ResourceFetcher::class);
    $fetcher->expects($this->once())
      ->method('refreshResource')
      ->with($url)
      ->willThrowException(new ResourceException('Could not retrieve the oEmbed resource.', $url));
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('warning')
      ->with('The oEmbed resource @url could not be refreshed: @message', [
        '@url' => $url,
        '@message' => 'Could not retrieve the oEmbed resource.',
      ]);

    $worker = new OEmbedResourceRefresher([], ResourceFetcher::QUEUE_NAME, [], $fetcher, $logger);
    // The exception is not rethrown, so the queue item is removed.
    $worker->processItem(['url' => $url]);
  }

  /**
   * Tests that other resource fetcher implementations are ignored.
   */
  public function testProcessItemOtherFetcher(): void {
    $fetcher = $this->createMock(ResourceFetcherInterface::class);
    $fetcher->expects($this->never())->method('fetchResource');
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->never())->method('warning');

    $worker = new OEmbedResourceRefresher([], ResourceFetcher::QUEUE_NAME, [], $fetcher, $logger);
    $worker->processItem(['url' => 'https://example.com/oembed?url=resource']);
  }

}
