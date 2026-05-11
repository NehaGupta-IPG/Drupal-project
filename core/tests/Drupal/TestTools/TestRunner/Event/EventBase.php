<?php

declare(strict_types=1);

namespace Drupal\TestTools\TestRunner\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * The base class for the test runeer events.
 *
 * @internal
 */
class EventBase extends Event {

  /**
   * The time of the event.
   */
  public readonly float $time;

  /**
   * The amount of memory currently in use.
   */
  public readonly int $memory;

  public function __construct() {
    $this->time = microtime(TRUE);
    $this->memory = memory_get_usage(TRUE);
  }

}
