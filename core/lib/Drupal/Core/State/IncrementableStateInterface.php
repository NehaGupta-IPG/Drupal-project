<?php

namespace Drupal\Core\State;

/**
 * Define an increment capability for the state system.
 */
interface IncrementableStateInterface extends StateInterface {

  /**
   * Increments the value of a given key.
   *
   * The increment and the read of the resulting value happen atomically. If
   * multiple processes increment the same key concurrently, each one receives
   * a unique value, in the order the increments were applied.
   *
   * If the key does not exist, it is created with a value of $step.
   *
   * Note: the returned value is only guaranteed to be unique among increments
   * that are committed. If the surrounding transaction is rolled back, the
   * same value may be returned again by a later call.
   *
   * @param string $key
   *   The key.
   * @param int $step
   *   The step to increment by.
   *
   * @return int
   *   The value after the increment.
   */
  public function increment(string $key, int $step = 1): int;

}
