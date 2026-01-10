<?php

namespace Drupal\Core\Ajax;

/**
 * An AJAX command to open certain content in a dialog in a modal dialog.
 *
 * @ingroup ajax
 */
class OpenModalDialogCommand extends OpenDialogCommand {

  public function __construct(string|\Stringable|null $title, $content, array $dialog_options = [], $settings = NULL) {
    $dialog_options['modal'] = TRUE;
    parent::__construct('#drupal-modal', $title, $content, $dialog_options, $settings);
  }

}
