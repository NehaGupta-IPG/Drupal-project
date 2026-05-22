<?php

namespace Drupal\mongodb\Plugin\views\argument;

/**
 * Trait for overriding the NumericArgument class.
 */
trait NumericArgumentTrait {

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();

    if (!empty($this->options['break_phrase'])) {
      $break = static::breakString($this->argument, FALSE);
      $this->value = $break->value;
      $this->operator = $break->operator;
    }
    else {
      $this->value = [$this->argument];
    }

    if ($this->table == $this->view->storage->get('base_table')) {
      $field = $this->realField;
    }
    else {
      $field = "$this->tableAlias.$this->realField";
    }

    if (count($this->value) > 1) {
      if (empty($this->options['not'])) {
        $this->query->addCondition(0, $field, $this->value, 'IN');
      }
      else {
        $or_condition = $this->query->getConnection()->condition('OR');
        $or_condition->condition($field, $this->value, 'NOT IN');
        $or_condition->isNull($field);
        $this->query->addCondition(0, $or_condition);
      }
    }
    else {
      if (empty($this->options['not'])) {
        $this->query->addCondition(0, $field, $this->argument, '=');
      }
      else {
        $or_condition = $this->query->getConnection()->condition('OR');
        $or_condition->condition($field, $this->argument, '!=');
        $or_condition->isNull($field);
        $this->query->addCondition(0, $or_condition);
      }
    }
  }

}
