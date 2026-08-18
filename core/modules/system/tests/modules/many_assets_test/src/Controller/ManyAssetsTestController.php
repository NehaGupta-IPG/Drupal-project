<?php

declare(strict_types=1);

namespace Drupal\many_assets_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\Attribute\Route;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a controller for testing asset loading order.
 */
class ManyAssetsTestController extends ControllerBase {

  /**
   * The render array of the assets testing page.
   *
   * @return array
   *   A render array with a message and attached asset libraries.
   */
  #[Route(
    path: '/many_assets_test',
    name: 'many_assets_test_page',
    title: new TranslatableMarkup('Test jQuery UI asset loading order'),
    requirements: ['_access' => 'TRUE'],
  )]
  public function build() {
    return [
      '#markup' => 'I am a page that tests loading order of many dependencies',
      '#attached' => [
        'library' => [
          'many_assets_test/weighted',
          'many_assets_test/many-dependencies',
          'many_assets_test/weighted_again',
        ],
      ],
    ];
  }

}
