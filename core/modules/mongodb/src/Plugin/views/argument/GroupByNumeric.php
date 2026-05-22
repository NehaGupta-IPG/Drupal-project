<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\GroupByNumeric as CoreGroupByNumeric;

/**
 * Overriding the views argument plugin "groupby_numeric".
 */
class GroupByNumeric extends CoreGroupByNumeric {

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();

    if ($this->table == $this->view->storage->get('base_table')) {
      $field = $this->realField;
    }
    else {
      $field = "$this->tableAlias.$this->realField";
    }

    $this->query->addHavingCondition(0, $field, $this->argument, '=');
  }

}
