<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge\EventSubscriber;

use Drupal\TestTools\Extension\DeprecationBridge\DeprecationHandler;
use PHPUnit\Event\Test\DeprecationTriggered;
use PHPUnit\Event\Test\DeprecationTriggeredSubscriber;

/**
 * Event subscriber notifying a E_USER_DEPRECATED triggered deprecation.
 *
 * @internal
 */
final class Deprecation implements DeprecationTriggeredSubscriber {

  public function notify(DeprecationTriggered $event): void {
    DeprecationHandler::deprecationTriggered($event);
  }

}
