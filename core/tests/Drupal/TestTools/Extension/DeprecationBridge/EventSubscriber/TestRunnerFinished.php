<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge\EventSubscriber;

use Drupal\TestTools\Extension\DeprecationBridge\DeprecationHandler;
use PHPUnit\Event\TestRunner\Finished;
use PHPUnit\Event\TestRunner\FinishedSubscriber;

/**
 * Event subscriber notifying end of test runner to DeprecationHandler.
 *
 * @internal
 */
final class TestRunnerFinished implements FinishedSubscriber {

  public function __construct(
    private DeprecationHandler $handler,
  ) {
  }

  public function notify(Finished $event): void {
    $this->handler->processStatisticsArtifact();
  }

}
