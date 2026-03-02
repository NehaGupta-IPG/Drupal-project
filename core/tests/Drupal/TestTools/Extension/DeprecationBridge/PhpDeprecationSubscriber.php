<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge;

use Drupal\TestTools\ErrorHandler\BootstrapErrorHandler;
use PHPUnit\Event\Test\PhpDeprecationTriggered;
use PHPUnit\Event\Test\PhpDeprecationTriggeredSubscriber;

/**
 * Event subscriber notifying a E_DEPRECATED triggered deprecation.
 *
 * @internal
 */
final class PhpDeprecationSubscriber implements PhpDeprecationTriggeredSubscriber {

  public function notify(PhpDeprecationTriggered $event): void {
    BootstrapErrorHandler::deprecationTriggered($event);
  }

}
