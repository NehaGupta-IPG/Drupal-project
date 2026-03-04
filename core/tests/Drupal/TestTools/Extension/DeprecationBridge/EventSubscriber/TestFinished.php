<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge\EventSubscriber;

use Drupal\TestTools\Extension\DeprecationBridge\DeprecationHandler;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;

/**
 * Event subscriber notifying finish of a test to DeprecationHandler.
 *
 * @internal
 */
final class TestFinished implements FinishedSubscriber {

  public function notify(Finished $event): void {
    DeprecationHandler::testFinished();
  }

}
