<?php

namespace Drupal\Core\State;

/**
 * Define an increment capability for the state system.
 */
interface IncrementableStateInterface extends StateInterface {

  /**
   * Increment the value of a given key.
   *
   * @param string $key
   *   The key.
   * @param int $step
   *   The step to increment by.
   */
  public function increment(string $key, int $step = 1): void;

}
