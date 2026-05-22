<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\mongodb\modules\views\ManyToOneHelper;
use Drupal\user\Plugin\views\argument\RolesRid as CoreRolesRid;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Overriding the views argument plugin "user__roles_rid".
 */
class RolesRid extends CoreRolesRid {

  use ManyToOneTrait;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->helper = new ManyToOneHelper($this);

    // Ensure defaults for these, during summaries and stuff:
    $this->operator = 'or';
    $this->value = [];
  }

}
