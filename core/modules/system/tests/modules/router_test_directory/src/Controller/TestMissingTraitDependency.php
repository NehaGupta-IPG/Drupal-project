<?php

declare(strict_types=1);

namespace Drupal\router_test\Controller;

use Drupal\a_module_that_does_not_exist\Controller\CustomTrait;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A controller that declares a route but uses a trait from a missing module.
 */
class TestMissingTraitDependency {

  use CustomTrait;

  /**
   * Builds something.
   *
   * @return array
   *   A render array.
   */
  #[Route('/test_missing_trait_dependency', name: 'router_test.missing_trait_dependency')]
  public function build(): array {
    return [];
  }

}
