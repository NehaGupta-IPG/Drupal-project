<?php

declare(strict_types=1);

namespace Drupal\new_dependency_test;

use Drupal\new_dependency_test_with_service\NewService;

/**
 * Generic service which uses setter injection.
 */
class SetterInjection {

  /**
   * The injected service.
   *
   * @var NewService
   */
  protected $service;

  /**
   * SetterInjection constructor.
   *
   * @param NewService $service
   *   The service of the new module.
   */
  public function setter(NewService $service) {
    $this->service = $service;
  }

  /**
   * Get the simple greeting from the service.
   *
   * @return string
   *   The greeting.
   */
  public function greet() {
    return $this->service->greet();
  }

}
