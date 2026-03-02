<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge;

use Drupal\TestTools\ErrorHandler\BootstrapErrorHandler;
use PHPUnit\Event\Test\DeprecationTriggered;
use PHPUnit\Event\Test\DeprecationTriggeredSubscriber;

/**
 * Event subscriber notifying a E_USER_DEPRECATED triggered deprecation.
 *
 * @internal
 */
final class DeprecationSubscriber implements DeprecationTriggeredSubscriber {

  public function notify(DeprecationTriggered $event): void {
    BootstrapErrorHandler::deprecationTriggered($event);
  }

}
