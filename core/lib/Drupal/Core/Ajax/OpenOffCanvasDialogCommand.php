<?php

namespace Drupal\Core\Ajax;

/**
 * Defines an AJAX command to open content in a dialog in an off-canvas tray.
 *
 * @ingroup ajax
 */
class OpenOffCanvasDialogCommand extends OpenDialogCommand {

  /**
   * The dialog width to use if none is provided.
   */
  const DEFAULT_DIALOG_WIDTH = 300;

  public function __construct(string|\Stringable|null $title, $content, array $dialog_options = [], $settings = NULL, $position = 'side') {
    $classes = [];
    if (isset($dialog_options['classes']['ui-dialog'])) {
      $classes[] = $dialog_options['classes']['ui-dialog'];
    }
    if (isset($dialog_options['dialogClass'])) {
      @trigger_error('Passing $dialog_options[\'dialogClass\'] to OpenOffCanvasDialogCommand::__construct() is deprecated in drupal:10.3.0 and will be removed in drupal:12.0.0. Use $dialog_options[\'classes\'] instead. See https://www.drupal.org/node/3440844', E_USER_DEPRECATED);
      $classes[] = $dialog_options['dialogClass'];
      unset($dialog_options['dialogClass']);
    }
    $classes[] = 'ui-dialog-off-canvas';
    $classes[] = "ui-dialog-position-$position";
    $dialog_options['classes']['ui-dialog'] = implode(' ', $classes);

    parent::__construct('#drupal-off-canvas', $title, $content, $dialog_options, $settings);
    $this->dialogOptions['modal'] = FALSE;
    $this->dialogOptions['autoResize'] = FALSE;
    $this->dialogOptions['resizable'] = 'w';
    $this->dialogOptions['draggable'] = FALSE;
    $this->dialogOptions['drupalAutoButtons'] = FALSE;
    $this->dialogOptions['drupalOffCanvasPosition'] = $position;

    // Add CSS class to #drupal-off-canvas element. This enables developers to
    // select previous versions of off-canvas styles by using custom selector:
    // #drupal-off-canvas:not(.drupal-off-canvas-reset).
    $this->dialogOptions['classes']['ui-dialog-content'] = 'drupal-off-canvas-reset';
    // If no width option is provided then use the default width to avoid the
    // dialog staying at the width of the previous instance when opened
    // more than once, with different widths, on a single page.
    if (!isset($this->dialogOptions['width'])) {
      $this->dialogOptions['width'] = static::DEFAULT_DIALOG_WIDTH;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['effect'] = 'fade';
    $build['speed'] = 1000;
    return $build;
  }

}
