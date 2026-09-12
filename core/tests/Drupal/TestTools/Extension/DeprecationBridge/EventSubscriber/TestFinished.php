<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge\EventSubscriber;

use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;

/**
 * Event subscriber notifying finish of a test to DeprecationHandler.
 *
 * @internal
 */
final class TestFinished extends SubscriberBase implements FinishedSubscriber {

  public function notify(Finished $event): void {
    $this->handler()->testFinished();
  }

}
