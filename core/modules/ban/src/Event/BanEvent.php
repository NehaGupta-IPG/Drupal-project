<?php

declare(strict_types=1);

namespace Drupal\ban\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event fired before an IP is persisted to the ban list.
 */
final class BanEvent extends Event {

  public const EVENT_NAME = 'ban_ip_event';

  /**
   * The affected IP address.
   *
   * @var string
   */
  public string $ip;

  /**
   * Whether the IP should be banned after subscribers run.
   *
   * @var bool
   */
  public bool $isBanned;

  public function __construct(string $ip, bool $isBanned = TRUE) {
    $this->ip = $ip;
    $this->isBanned = $isBanned;
  }

}
