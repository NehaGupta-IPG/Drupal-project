<?php

declare(strict_types=1);

namespace Drupal\services_defaults_test;

/**
 * An autowired service to test _defaults.
 */
class TestService {

  public function __construct(
    protected TestInjectionInterface $testInjection,
    protected TestInjection2 $testInjection2,
  ) {}

  public function getTestInjection() {
    return $this->testInjection;
  }

  public function getTestInjection2() {
    return $this->testInjection2;
  }

}
