<?php

namespace Drupal\mongodb\Plugin\views\argument;

/**
 * Trait for overriding the FilterPluginBase class.
 */
trait ArgumentPluginTrait {

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
    $this->query->addCondition(0, $field, $this->argument);
  }

}
