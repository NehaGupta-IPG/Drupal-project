<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge\EventSubscriber;

use PHPUnit\Event\Test\PhpDeprecationTriggered;
use PHPUnit\Event\Test\PhpDeprecationTriggeredSubscriber;

/**
 * Event subscriber notifying a E_DEPRECATED triggered deprecation.
 *
 * @internal
 */
final class PhpDeprecation extends SubscriberBase implements PhpDeprecationTriggeredSubscriber {

  public function notify(PhpDeprecationTriggered $event): void {
    $this->handler()->deprecationTriggered($event);
  }

}
