<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge\EventSubscriber;

use PHPUnit\Event\TestRunner\Finished;
use PHPUnit\Event\TestRunner\FinishedSubscriber;

/**
 * Event subscriber notifying end of test runner to DeprecationHandler.
 *
 * @internal
 */
final class TestRunnerFinished extends SubscriberBase implements FinishedSubscriber {

  public function notify(Finished $event): void {
    $this->handler()->processStatisticsArtifact();
  }

}
