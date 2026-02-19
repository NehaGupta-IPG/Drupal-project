<?php

namespace Drupal\Core\Queue;

/**
 * An interface defining queue factory classes.
 */
interface QueueFactoryInterface {

  /**
   * Constructs a new queue object for a given name.
   *
   * @param string $name
   *   The name of the queue.
   *
   * @return QueueInterface
   *   The queue object.
   */
  public function get($name);

}
