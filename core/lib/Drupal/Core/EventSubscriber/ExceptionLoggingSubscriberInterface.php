<?php

namespace Drupal\Core\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Interface for event subscriber that handles logging exceptions.
 */
interface ExceptionLoggingSubscriberInterface extends EventSubscriberInterface {

  /**
   * Log all exceptions.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function onException(ExceptionEvent $event): void;

}
