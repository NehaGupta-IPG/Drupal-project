<?php

namespace Drupal\Core\Render\MainContent;

use Symfony\Component\HttpFoundation\Request;

/**
 * Extend the dialog renderer to return a modal dialog.
 */
class HtmxOffCanvasRenderer extends HtmxDialogRenderer implements MainContentRendererInterface {

  /**
   * {@inheritdoc}
   */
  protected function generateDialog(array $main_content, string $dialogId, mixed $title, Request $request): array {
    $dialog = parent::generateDialog($main_content, $dialogId, $title, $request);
    $dialog['#variant'] = 'off-canvas';
    return $dialog;
  }

}
