<?php

namespace Drupal\Core\Database;

use Drupal\Core\Database\EventSubscriber\StatementExecutionSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Fixed event dispatch factory class.
 */
class FixedEventDispatcherFactory {

  /**
   * Subscribers to be registered for early event dispatcher.
   */
  const array SUBSCRIBERS = [
    StatementExecutionSubscriber::class,
  ];

  /**
   * Construct a new FixedEventDispatcherFactory object.
   */
  public static function getEventDispatcher(): EventDispatcherInterface {
    $eventDispatcher = new EventDispatcher();
    foreach (self::SUBSCRIBERS as $subscriber) {
      $eventDispatcher->addSubscriber(new $subscriber());
    }
    return $eventDispatcher;
  }

}
