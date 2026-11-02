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
   * Displays test elements that will open in a dialog.
   *
   * @return array
   *   Render array.
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

    $modalFormUrl = Url::fromUri('base:/htmx/dialog/modal/dialog_renderer-form');
    $build['modal_form'] = [
      '#title' => 'Form in a modal',
      '#type' => 'checkbox',
      '#name' => 'modal_form',
    ];
    (new Htmx())->get($modalFormUrl)
      ->applyTo($build['modal_form']);

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

    $offCanvasFormUrl = Url::fromUri('base:/htmx/dialog/off-canvas/dialog_renderer-form');
    $build['side_off_canvas_form'] = [
      '#title' => 'Form in an off-canvas side',
      '#type' => 'checkbox',
      '#name' => 'side_off_canvas_form',
    ];
    (new Htmx())->get($offCanvasFormUrl)
      ->applyTo($build['side_off_canvas_form']);

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

}
