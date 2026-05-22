<?php

namespace Drupal\mongodb\Plugin\views\row;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\row\EntityRow as CoreEntityRow;
use Drupal\views\ViewExecutable;

/**
 * Overrides the views row plugin "entity".
 */
class EntityRow extends CoreEntityRow {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->base_table = $this->entityType->getBaseTable();
  }

}
