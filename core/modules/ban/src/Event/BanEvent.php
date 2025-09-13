<?php

namespace Drupal\ban\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event fired before an IP is persisted to the ban list.
 */
class BanEvent extends Event {

  public const EVENT_NAME = 'ban_ip_event';

  public string $ip;
  public bool $isBanned;

  public function __construct(string $ip, bool $isBanned = TRUE) {
    $this->ip = $ip;
    $this->isBanned = $isBanned;
  }

}
