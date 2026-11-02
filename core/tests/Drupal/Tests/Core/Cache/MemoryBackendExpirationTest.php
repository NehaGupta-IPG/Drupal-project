<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Cache;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests MemoryBackend cache expiration with getCurrentTime.
 */
#[CoversMethod(MemoryBackend::class, 'prepareItem')]
#[Group('Cache')]
class MemoryBackendExpirationTest extends UnitTestCase {

  /**
   * Tests cache items expire based on current time, not request time.
   */
  public function testCacheExpirationWithCurrentTime(): void {
    $time = $this->createMock(TimeInterface::class);
    $time->expects($this->exactly(3))
      ->method('getCurrentTime')
      ->willReturnOnConsecutiveCalls(1000, 1000, 1005);

    $backend = new MemoryBackend($time);
    $backend->set('key', 'value', 1002);

    $this->assertNotFalse($backend->get('key'), 'Cache valid before expiration.');
    $this->assertFalse($backend->get('key'), 'Cache expired after TTL.');
  }

  /**
   * Tests permanent cache items never expire.
   */
  public function testPermanentCacheDoesNotExpire(): void {
    $time = $this->createStub(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(PHP_INT_MAX);

    $backend = new MemoryBackend($time);
    $backend->set('key', 'value');

    $this->assertNotFalse($backend->get('key'), 'Permanent cache never expires.');
  }

}
