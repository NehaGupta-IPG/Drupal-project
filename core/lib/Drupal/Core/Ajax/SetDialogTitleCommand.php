<?php

namespace Drupal\Core\Ajax;

/**
 * Defines an AJAX command that sets jQuery UI dialog properties.
 *
 * @ingroup ajax
 */
class SetDialogTitleCommand extends SetDialogOptionCommand {

  public function __construct($selector, $title) {
    $this->selector = $selector ?: '#drupal-modal';
    $this->optionName = 'title';
    $this->optionValue = $title;
  }

}
