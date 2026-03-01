<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge;

use Drupal\TestTools\ErrorHandler\BootstrapErrorHandler;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;

/**
 * Event subscriber notifying finish of a test to DeprecationHandler.
 *
 * @internal
 */
final class TestFinishedSubscriber implements FinishedSubscriber {

  public function notify(Finished $event): void {
    BootstrapErrorHandler::setCurrentTest(NULL);
  }

}
