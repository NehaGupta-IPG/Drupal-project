<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge\EventSubscriber;

use Drupal\TestTools\Extension\DeprecationBridge\DeprecationHandler;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;

/**
 * Event subscriber notifying preparation of a test to DeprecationHandler.
 *
 * @internal
 */
final class TestPreparationStarted implements PreparationStartedSubscriber {

  public function notify(PreparationStarted $event): void {
    DeprecationHandler::setCurrentTest($event->test());
  }

}
