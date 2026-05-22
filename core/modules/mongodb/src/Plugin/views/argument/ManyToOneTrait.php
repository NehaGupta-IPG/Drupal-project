<?php

namespace Drupal\mongodb\Plugin\views\argument;

/**
 * Trait for overriding the ManyToOne class.
 */
trait ManyToOneTrait {

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    if ($this->table == $this->view->storage->get('base_table')) {
      $field = $this->realField;
    }
    else {
      $field = "$this->tableAlias.$this->realField";
    }

    $empty = FALSE;
    if (isset($this->definition['zero is null']) && $this->definition['zero is null']) {
      if (empty($this->argument)) {
        $empty = TRUE;
      }
    }
    else {
      if (!isset($this->argument)) {
        $empty = TRUE;
      }
    }
    if ($empty) {
      parent::ensureMyTable();
      $this->query->addCondition(0, $field, NULL, 'IS NULL');
      return;
    }

    if (!empty($this->options['break_phrase'])) {
      $force_int = !empty($this->definition['numeric']);
      $this->unpackArgumentValue($force_int);
    }
    else {
      $this->value = [$this->argument];
      $this->operator = 'or';
    }

    $this->helper->addFilter();
  }

}
