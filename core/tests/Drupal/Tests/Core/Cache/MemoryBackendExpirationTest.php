<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Cache;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Tests\UnitTestCase;

/**
 * Tests MemoryBackend cache expiration with getCurrentTime.
 *
 * @coversDefaultClass \Drupal\Core\Cache\MemoryBackend
 * @group Cache
 */
class MemoryBackendExpirationTest extends UnitTestCase {

  /**
   * Tests cache items expire based on current time, not request time.
   *
   * @covers ::prepareItem
   */
  public function testCacheExpirationWithCurrentTime(): void {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturnOnConsecutiveCalls(1000, 1000, 1005);

    $backend = new MemoryBackend('test', $time);
    $backend->set('key', 'value', 1002);

    $this->assertNotFalse($backend->get('key'), 'Cache valid before expiration.');
    $this->assertFalse($backend->get('key'), 'Cache expired after TTL.');
  }

  /**
   * Tests permanent cache items never expire.
   *
   * @covers ::prepareItem
   */
  public function testPermanentCacheDoesNotExpire(): void {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(PHP_INT_MAX);

    $backend = new MemoryBackend('test', $time);
    $backend->set('key', 'value', Cache::PERMANENT);

    $this->assertNotFalse($backend->get('key'), 'Permanent cache never expires.');
  }
}
