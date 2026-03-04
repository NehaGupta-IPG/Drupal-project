<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge\EventSubscriber;

use Drupal\TestTools\Extension\DeprecationBridge\DeprecationHandler;

/**
 * Base class for PHPUnit event subscribers related to DeprecationHandler.
 *
 * @internal
 */
abstract class SubscriberBase {

  public function __construct(
    private readonly DeprecationHandler $handler,
  ) {
  }

  protected function handler(): DeprecationHandler {
    return $this->handler;
  }

}
