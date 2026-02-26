<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge;

use PHPUnit\Event\TestRunner\Finished;
use PHPUnit\Event\TestRunner\FinishedSubscriber;

/**
 * Event subscriber notifying end of test runner to DeprecationHandler.
 *
 * @internal
 */
final class TestRunnerFinishedSubscriber implements FinishedSubscriber {

  public function __construct() {
  }

  public function notify(Finished $event): void {
    DeprecationHandler::processStatisticsArtifact();
  }

}
