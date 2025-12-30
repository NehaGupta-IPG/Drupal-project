<?php

namespace Drupal\Core\Ajax;

/**
 * Defines an AJAX command that closes the currently visible modal dialog.
 *
 * @ingroup ajax
 */
class CloseModalDialogCommand extends CloseDialogCommand {

  public function __construct($persist = FALSE) {
    $this->selector = '#drupal-modal';
    $this->persist = $persist;
  }

}
