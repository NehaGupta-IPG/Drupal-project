<?php

declare(strict_types=1);

namespace Drupal\dialog_renderer_test\Controller;

use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;

/**
 * Test controller to display dialog links and content.
 */
class HtmxDialogTestController {

  /**
   * Return dialog content.
   *
   * @return array
   *   Render array for display in dialog.
   */
  public function dialogContent() {
    return [
      '#type' => 'markup',
      '#markup' => 'Look at me in a dialog!',
    ];
  }

  /**
   * Return dialog content with link.
   *
   * @return array
   *   Render array for display in dialog.
   */
  public function dialogContentLink() {
    return [
      '#type' => 'container',
      'text' => [
        '#type' => 'markup',
        '#markup' => 'Look at me in a dialog!<br><a href="#">And a link!</a>',
      ],
      'input' => [
        '#type' => 'textfield',
        '#size' => 60,
      ],
    ];
  }

  /**
   * Return dialog content with autofocus input.
   *
   * @return array
   *   Render array for display in dialog.
   */
  public function dialogContentInput() {
    return [
      '#type' => 'container',
      'text' => [
        '#type' => 'markup',
        '#markup' => 'Look at me in a dialog!<br><a href="#">And a link!</a>',
      ],
      'input' => [
        '#type' => 'textfield',
        '#size' => 60,
        '#attributes' => [
          'autofocus' => TRUE,
        ],
      ],
    ];
  }

  /**
   * Displays test links that will open in a dialog.
   *
   * @return array
   *   Render array with links.
   */
  public function testMarkup() {
    $build['content'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => 'The quick brown fox jumped over the lazy dog. The quick brown fox jumped over the lazy dog. The quick brown fox jumped over the lazy dog. The quick brown fox jumped over the lazy dog. The quick brown fox jumped over the lazy dog. The quick brown fox jumped over the lazy dog.',
    ];
    $modalUrl = Url::fromUri('base:/htmx/dialog/modal/htmx-dialog-content');
    $build['normal_modal'] = [
      '#title' => 'Normal Modal',
      '#type' => 'checkbox',
      '#name' => 'normal_modal',
    ];
    (new Htmx())->get($modalUrl)
      ->applyTo($build['normal_modal']);

    $build['styled_modal_single_class'] = [
      '#title' => 'Modal with single additional class',
      '#type' => 'checkbox',
      '#name' => 'styled_modal_single_class',
    ];
    (new Htmx())->get($modalUrl)
      ->vals(['class' => 'dialog--wide'])
      ->applyTo($build['styled_modal_single_class']);

    $defaultUrl = Url::fromUri('base:/htmx/dialog/default/htmx-dialog-content');
    $build['normal_dialog'] = [
      '#title' => 'Default dialog',
      '#type' => 'checkbox',
      '#name' => 'normal_dialog',
    ];
    (new Htmx())->get($defaultUrl)
      ->applyTo($build['normal_dialog']);

    $offCanvasUrl = Url::fromUri('base:/htmx/dialog/off-canvas/htmx-dialog-content');
    $build['side_off_canvas'] = [
      '#title' => 'Off-canvas side',
      '#type' => 'checkbox',
      '#name' => 'side_off_canvas',
    ];
    (new Htmx())->get($offCanvasUrl)
      ->applyTo($build['side_off_canvas']);

    $offCanvasTopUrl = Url::fromUri('base:/htmx/dialog/off-canvas-top/htmx-dialog-content', ['query' => ['position' => 'top']]);
    $build['top_off_canvas'] = [
      '#title' => 'Off-canvas top',
      '#type' => 'checkbox',
      '#name' => 'top_off_canvas',
    ];
    (new Htmx())->get($offCanvasTopUrl)
      ->applyTo($build['top_off_canvas']);

    return $build;
  }

  /**
   * Displays a dropbutton with a link that opens in a modal dialog.
   *
   * @return array
   *   Render array with links.
   */
  public function collapsedOpener() {
    return [
      '#markup' => '<h2>Honk</h2>',
      'dropbutton' => [
        '#type' => 'dropbutton',
        '#dropbutton_type' => 'small',
        '#links' => [
          'front' => [
            'title' => 'front!',
            'url' => Url::fromRoute('<front>'),
          ],
          'in a dropbutton' => [
            'title' => 'inside a dropbutton',
            'url' => Url::fromRoute('dialog_renderer_test.dialog_content'),
            'attributes' => [
              'class' => ['use-ajax'],
              'data-dialog-type' => 'modal',
            ],
          ],
        ],
      ],
      '#attached' => [
        'library' => [
          'core/drupal.ajax',
        ],
      ],
    ];
  }

}
