<?php

declare(strict_types=1);

namespace Drupal\dialog_renderer_test\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;

/**
 * Test controller to display dialog links and content.
 */
class HtmxDialogController {

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
  public function linksDisplay() {
    $url = Url::fromUri('internal:/htmx/dialog/modal/htmx-dialog-content');
    $build = [
      'normal_modal' => [
        '#title' => 'Normal Modal!',
        '#type' => 'checkbox',
      ],
      'wide_dialog' => [
        '#title' => 'Wide dialog!',
        '#type' => 'link',
        '#url' => Url::fromRoute('dialog_renderer_test.dialog_content'),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-renderer' => 'wide',
        ],
        '#attached' => [
          'library' => [
            'core/drupal.ajax',
          ],
        ],
      ],
      'extra_wide_dialog' => [
        '#title' => 'Extra Wide dialog!',
        '#type' => 'link',
        '#url' => Url::fromRoute('dialog_renderer_test.dialog_content'),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-renderer' => 'extra_wide',
        ],
        '#attached' => [
          'library' => [
            'core/drupal.ajax',
          ],
        ],
      ],
      'no_close_modal' => [
        '#title' => 'Hidden close button modal!',
        '#type' => 'link',
        '#url' => Url::fromRoute('dialog_renderer_test.dialog_content'),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'classes' => [
              'ui-dialog' => 'no-close',
            ],
          ]),
        ],
        '#attached' => [
          'library' => [
            'core/drupal.ajax',
            'dialog_renderer_test/dialog_test',
          ],
        ],
      ],
      'button_pane_modal' => [
        '#title' => 'Button pane modal!',
        '#type' => 'link',
        '#url' => Url::fromRoute('dialog_renderer_test.dialog_content'),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'buttons' => [
              [
                'text' => 'OK',
                'click' => '() => {}',
              ],
            ],
          ]),
        ],
        '#attached' => [
          'library' => [
            'core/drupal.ajax',
          ],
        ],
      ],
      'content_link_dialog' => [
        '#title' => 'Content link dialog!',
        '#type' => 'link',
        '#url' => Url::fromRoute('dialog_renderer_test.dialog_content_link'),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
        ],
        '#attached' => [
          'library' => [
            'core/drupal.ajax',
          ],
        ],
      ],
      'auto_focus_dialog' => [
        '#title' => 'Auto focus dialog!',
        '#type' => 'link',
        '#url' => Url::fromRoute('dialog_renderer_test.dialog_content_input'),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
        ],
        '#attached' => [
          'library' => [
            'core/drupal.ajax',
          ],
        ],
      ],
      'auto_buttons_default' => [
        '#title' => 'Auto buttons default!',
        '#type' => 'link',
        '#url' => Url::fromRoute('dialog_renderer_test.modal_form'),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'dialog',
        ],
        '#attached' => [
          'library' => [
            'core/drupal.ajax',
          ],
        ],
      ],
      'auto_buttons_false' => [
        '#title' => 'Auto buttons false!',
        '#type' => 'link',
        '#url' => Url::fromRoute('dialog_renderer_test.modal_form'),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'dialog',
          'data-dialog-options' => Json::encode([
            'drupalAutoButtons' => FALSE,
          ]),
        ],
        '#attached' => [
          'library' => [
            'core/drupal.ajax',
          ],
        ],
      ],
      'auto_buttons_true' => [
        '#title' => 'Auto buttons true!',
        '#type' => 'link',
        '#url' => Url::fromRoute('dialog_renderer_test.modal_form'),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'dialog',
          'data-dialog-options' => Json::encode([
            'drupalAutoButtons' => TRUE,
          ]),
        ],
        '#attached' => [
          'library' => [
            'core/drupal.ajax',
          ],
        ],
      ],
    ];
    (new Htmx())->get($url)
      ->applyTo($build['normal_modal']);
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
