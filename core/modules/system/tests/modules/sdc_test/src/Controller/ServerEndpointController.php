<?php

declare(strict_types=1);

namespace Drupal\sdc_test\Controller;

use Drupal\Core\Routing\Attribute\Route;

/**
 * An endpoint to serve a component during tests.
 *
 * @internal
 */
final class ServerEndpointController {

  /**
   * Render an arbitrary render array.
   */
  #[Route(
    path: '/sdc-test-component',
    name: 'sdc_test.array_render',
    requirements: ['_access' => 'TRUE'],
  )]
  public function renderArray(): array {
    $render_array = \Drupal::state()->get('sdc_test_component', ['#markup' => 'Set your component in state using the sdc_test_component key.']);
    return [
      '#type' => 'container',
      '#cache' => ['max-age' => 0],
      // Magic wrapper ID to pull the HTML from.
      '#attributes' => ['id' => 'sdc-wrapper'],
      'component' => $render_array,
    ];
  }

}
