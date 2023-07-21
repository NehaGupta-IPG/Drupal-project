<?php

declare(strict_types = 1);

namespace Drupal\services_bind_test;

/**
 * Test service class with different dependencies.
 */
class TestService {

  public function __construct(
    public readonly TestInjection $testInjection,
    public readonly TestInjection $testInjectionOther,
    public readonly string $testContainerParameter,
    public readonly string $testString,
    public readonly int $testValue,
  ) {}

}
