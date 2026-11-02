<?php

declare(strict_types=1);

namespace Drupal\router_test\Controller;

use Symfony\Component\Routing\Attribute\Route;

/**
 * An abstract controller, which can never be instantiated to serve a route.
 */
abstract class TestAbstractController {

  /**
   * Builds something.
   *
   * @return array
   *   A render array.
   */
  #[Route('/test_abstract', name: 'router_test.abstract')]
  public function build(): array {
    return [];
  }

}
