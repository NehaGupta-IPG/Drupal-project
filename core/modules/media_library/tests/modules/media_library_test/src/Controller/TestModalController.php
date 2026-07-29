<?php

declare(strict_types=1);

namespace Drupal\media_library_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Test controller for opening a node form in a modal dialog.
 */
class TestModalController extends ControllerBase {

  /**
   * Builds a page with a link that opens the basic_page form in a modal.
   *
   * Used to exercise the media library widget when it is rendered inside
   * another modal dialog.
   *
   * @return array
   *   A render array.
   */
  public function modal(): array {
    return [
      'basic_page' => [
        '#type' => 'link',
        '#title' => $this->t('Add basic page'),
        '#url' => Url::fromRoute('node.add', ['node_type' => 'basic_page']),
        '#attributes' => [
          'class' => ['use-ajax', 'modal-basic_page'],
          'data-dialog-type' => 'modal',
        ],
        '#attached' => [
          'library' => ['core/drupal.dialog.ajax'],
        ],
      ],
    ];
  }

}
