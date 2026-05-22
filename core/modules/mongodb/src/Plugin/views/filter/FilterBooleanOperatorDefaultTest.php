<?php

namespace Drupal\mongodb\Plugin\views\filter;

/**
 * Overriding the views filter plugin "boolean_default".
 */
class FilterBooleanOperatorDefaultTest extends BooleanOperator {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    if ($this->table == $this->view->storage->get('base_table')) {
      $field = $this->realField;
    }
    else {
      $field = "$this->tableAlias.$this->realField";
    }

    $this->queryOpBoolean($field);
  }

}
