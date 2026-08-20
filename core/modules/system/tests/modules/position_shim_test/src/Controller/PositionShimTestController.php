<?php

declare(strict_types=1);

namespace Drupal\position_shim_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\Attribute\Route;

/**
 * The position shim test controller.
 */
class PositionShimTestController extends ControllerBase {

  /**
   * Provides a page with the jQuery UI position library for testing.
   *
   * @return array
   *   The render array.
   */
  #[Route(
    path: '/position-shim-test',
    name: 'position_test_page',
    requirements: ['_access' => 'TRUE'],
  )]
  public function build() {
    return [
      'reference1' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'position-reference-1',
        ],
      ],
      '#attached' => [
        'library' => [
          'core/drupal.jquery.position',
          'position_shim_test/position.shim.test',
        ],
      ],
    ];
  }

}
