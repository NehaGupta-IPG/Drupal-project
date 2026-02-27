<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge;

use PHPUnit\Event\TestRunner\Started;
use PHPUnit\Event\TestRunner\StartedSubscriber;

/**
 * Event subscriber notifying start of test runner to DeprecationHandler.
 *
 * @internal
 */
final class TestRunnerStartedSubscriber implements StartedSubscriber {

  public function __construct() {
  }

  public function notify(Started $event): void {
    DeprecationHandler::runnerStarted();
  }

}
