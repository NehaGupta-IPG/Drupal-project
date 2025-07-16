<?php

namespace Drupal\Core\Environment;

/**
 * Interface for environment managers.
 */
interface EnvironmentManagerInterface {

  /**
   * Invokes the given callback after recursively applying the substitutions.
   *
   * @param \Drupal\Core\Environment\EnvironmentParameter[] $params
   *   List of environment parameters to apply to suitable substitutions.
   * @param callable(): T $callback
   *   Callback to execute inside substituted environment.
   *
   * @return T
   *   Returns the result returned by the callback.
   *
   * @template T
   */
  public function executeInEnvironments(array $params, callable $callback): mixed;

}
