<?php

namespace Drupal\Core\Render\MainContent;

/**
 * Extend the dialog renderer to return a modal dialog.
 */
class HtmxModalRenderer extends HtmxDialogRenderer implements MainContentRendererInterface {

  /**
   * {@inheritdoc}
   */
  protected function generateDialog(array $main_content, string $dialogId, mixed $title): array {
    $dialog = parent::generateDialog($main_content, $dialogId, $title);
    $dialog['#variant'] = 'modal';
    return $dialog;
  }

}
