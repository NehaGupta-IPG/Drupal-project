<?php

declare(strict_types=1);

namespace Drupal\importmap_test;

use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a controller for testing importmap.
 */
final class TestController {

  /**
   * Invoke the controller.
   */
  public function __invoke(Request $request): array {
    return [
      '#markup' => '<div id="importmap-test"></div>',
      '#cache' => [
        'contexts' => ['url.query_args:scoped'],
      ],
      '#attached' => [
        'library' => ['importmap_test/foo' . ($request->query->has('scoped') ? '-scoped' : '')],
      ],
    ];
  }

}
