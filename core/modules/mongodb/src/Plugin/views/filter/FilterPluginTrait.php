<?php

namespace Drupal\mongodb\Plugin\views\filter;

/**
 * Trait for overriding the FilterPluginBase class.
 */
trait FilterPluginTrait {

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
    $this->query->addCondition($this->options['group'], $field, $this->value, $this->operator);
  }

}
