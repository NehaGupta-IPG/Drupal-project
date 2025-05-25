<?php

declare(strict_types=1);

namespace Drupal\hook_collector_hook_attribute;

/**
 * Trait used to track how often a hook implementation method has run.
 */
trait HookInvocationsCountTrait {

  /**
   * Array of how often methods have run, indexed by method name.
   *
   * @var array{string, int}
   */
  protected array $countInvocations = [];

  /**
   * Increments the run counter for a method.
   *
   * @param string $method
   *   The method name.
   * @param array{string, int} $invocations
   *   Count of how many times a method has been invoked, indexed by method.
   *   Passed by reference.
   */
  protected function incrementRunCount(string $method, array &$invocations): void {
    $this->countInvocations[$method] ??= 0;
    $this->countInvocations[$method]++;
    $invocations[$method] = $this->countInvocations[$method];
  }

}
