<?php

declare(strict_types=1);

namespace Drupal\ckeditor5_test\Controller;

use Drupal\Core\Routing\Attribute\Route;
use Drupal\Core\Url;

/**
 * Provides controller for testing CKEditor in off-canvas dialogs.
 */
class CKEditor5OffCanvasTestController {

  /**
   * Returns a link that can open a node add form in an off-canvas dialog.
   *
   * @return array
   *   A render array.
   */
  #[Route(
    path: '/ckeditor5_test/off_canvas',
    name: 'ckeditor5_test.off_canvas',
    requirements: ['_access' => 'TRUE'],
  )]
  public function testOffCanvas() {
    $build['link'] = [
      '#type' => 'link',
      '#title' => 'Add Node',
      '#url' => Url::fromRoute('entity.node.add_form', ['node_type' => 'page']),
      '#attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'dialog',
        'data-dialog-renderer' => 'off_canvas',
      ],
    ];
    $build['#attached']['library'][] = 'core/drupal.dialog.off_canvas';
    return $build;
  }

}
