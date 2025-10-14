<?php

declare(strict_types=1);

namespace Drupal\TestTools\Mocks;

use Drupal\Component\Datetime\Time;

/**
 * Test service for mocking the request time in kernel tests.
 *
 * Use either setRequestTime() or incrementRequestTime() to mock the request
 * time.
 */
class TestMockedTime extends Time {

  /**
   * The mocked request time.
   *
   * The time should not be 0, because \Drupal\Core\Cache\MemoryBackend
   * subtracts 1 from cache item expiry time to invalidate cache items, and -1
   * means permanent.
   */
  protected int $requestTime = 10_000;

  /**
   * Sets the request time.
   *
   * @param int $new_time
   *   The new timestamp for the request time.
   */
  public function setRequestTime(int $new_time): void {
    $this->requestTime = $new_time;
  }

  /**
   * Increases the request time.
   */
  public function incrementRequestTime(): void {
    $this->requestTime += 10_000;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestTime() {
    return $this->requestTime;
  }

}
