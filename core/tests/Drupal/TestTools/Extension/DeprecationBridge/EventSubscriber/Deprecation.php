<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge\EventSubscriber;

use PHPUnit\Event\Test\DeprecationTriggered;
use PHPUnit\Event\Test\DeprecationTriggeredSubscriber;

/**
 * Event subscriber notifying a E_USER_DEPRECATED triggered deprecation.
 *
 * @internal
 */
final class Deprecation extends SubscriberBase implements DeprecationTriggeredSubscriber {

  public function notify(DeprecationTriggered $event): void {
    $this->handler()->deprecationTriggered($event);
  }

}
