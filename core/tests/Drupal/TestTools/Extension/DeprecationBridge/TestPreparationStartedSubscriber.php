<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge;

use Drupal\TestTools\ErrorHandler\BootstrapErrorHandler;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;

/**
 * Event subscriber notifying preparation of a test to DeprecationHandler.
 *
 * @internal
 */
final class TestPreparationStartedSubscriber implements PreparationStartedSubscriber {

  public function notify(PreparationStarted $event): void {
    BootstrapErrorHandler::setCurrentTest($event->test());
  }

}
