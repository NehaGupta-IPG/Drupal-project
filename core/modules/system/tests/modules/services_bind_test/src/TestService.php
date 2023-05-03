<?php

declare(strict_types = 1);

namespace Drupal\services_bind_test;

/**
 * Test service class with different dependencies.
 */
class TestService {

  public function __construct(
    private readonly TestInjection $testInjection,
    private readonly TestInjection $testInjectionOther,
    private readonly string $testContainerParameter,
    private readonly string $testString,
    private readonly int $testValue,
  ) {}

  public function export(): array {
    return [
      $this->testInjection->id,
      $this->testInjectionOther->id,
      $this->testContainerParameter,
      $this->testString,
      $this->testValue,
    ];
  }

}
