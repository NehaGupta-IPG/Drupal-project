<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\user\Plugin\views\filter\Current as CoreCurrent;

/**
 * Overriding the views filter plugin "user_current".
 */
class Current extends CoreCurrent {

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

    $or = $this->view->query->getConnection()->condition('OR');

    if (empty($this->value)) {
      $or->condition($field, '***CURRENT_USER***', '<>');
      if ($this->accept_null) {
        $or->isNull($field);
      }
    }
    else {
      $or->condition($field, '***CURRENT_USER***', '=');
    }
    $this->query->addCondition($this->options['group'], $or);
  }

}
