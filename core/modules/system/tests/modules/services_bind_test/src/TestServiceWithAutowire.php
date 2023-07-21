<?php

declare(strict_types = 1);

namespace Drupal\services_bind_test;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Test service class with different dependencies.
 */
class TestServiceWithAutowire {

  public function __construct(
    public readonly TestInjectionAutowire $testInjectionAutowire,
    public readonly TestInjectionAutowire $testInjectionAutowire1,
    #[Autowire('@services_bind_test.test_injection_autowire.attr')]
    public readonly TestInjectionAutowire $testInjectionAutowireAttr,
  ) {}

}
