<?php

declare(strict_types=1);

namespace Drupal\new_dependency_test;

/**
 * Service that gets the other service of the same module injected.
 *
 * This service indirectly depends on a not-yet-defined service.
 */
class ServiceWithDependency {

  /**
   * The injected service.
   *
   * @var \Drupal\new_dependency_test\InjectedService
   */
  protected $service;

  public function __construct(?InjectedService $service = NULL) {
    $this->service = $service;
  }

  /**
   * Gets a greeting from the injected service and adds to it.
   *
   * @return string
   *   The greeting.
   */
  public function greet() {
    if (isset($this->service)) {
      return $this->service->greet() . ' World';
    }
    return 'Sorry, no service.';
  }

}
