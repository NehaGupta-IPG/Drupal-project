<?php

namespace Drupal\Core\KeyValueStore;

/**
 * Define an increment capability for the key-value system.
 */
interface IncrementableKeyValueStoreInterface extends KeyValueStoreInterface {

  /**
   * Increment the value of a key.
   *
   * @param string $key
   *   The key.
   * @param int $step
   *   The value to increment by.
   */
  public function increment(string $key, int $step = 1): void;

}
