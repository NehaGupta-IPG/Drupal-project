<?php

declare(strict_types=1);

namespace Drupal\importmaps_test;

use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a controller for testing importmaps.
 */
final class TestController {

  /**
   * Invoke the controller.
   */
  public function __invoke(Request $request): array {
    return [
      '#markup' => '<div id="importmaps-test"></div>',
      '#cache' => [
        'contexts' => ['url.query_args:scoped'],
      ],
      '#attached' => [
        'library' => ['importmaps_test/foo' . ($request->query->has('scoped') ? '-scoped' : '')],
      ],
    ];
  }

}
