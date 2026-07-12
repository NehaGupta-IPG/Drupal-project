<?php

namespace Drupal\Core\Environment;

/**
 * Interface for services which substitute parts of the execution environment.
 */
interface SubstitutionInterface {

  /**
   * Returns TRUE if the substitution can handle the given parameter.
   *
   * @param EnvironmentParameter $param
   *   The environment parameter to check.
   *
   * @return bool
   *   Whether this substitution handles the given environment parameter.
   */
  public function applies(EnvironmentParameter $param): bool;

  /**
   * Executes a callback after applying the substitution.
   *
   * @param EnvironmentParameter $param
   *   The environment parameter to apply.
   * @param callable(): T $callback
   *   The callback to run.
   *
   * @return T
   *   Returns the result returned by the callback.
   *
   * @template T
   */
  public function executeInEnvironment(EnvironmentParameter $param, callable $callback): mixed;

}
