<?php

namespace Drupal\mongodb\Plugin\views\filter;

/**
 * Trait for overriding the \Drupal\views\Plugin\views\filter\Date class.
 */
trait DateTrait {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $field = $this->realField;

    if (!empty($this->query->getCurrentRevisionTable()) && !empty($this->query->getAllRevisionsTable())) {
      $search_needle = $this->query->getCurrentRevisionTable() . '.';
      $replace_needle = $this->query->getAllRevisionsTable() . '.';
      $field = str_replace($search_needle, $replace_needle, $field);
    }

    $info = $this->operators();
    if (!empty($info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opEmpty($field) {
    if ($this->operator == 'empty') {
      $operator = "IS NULL";
    }
    else {
      $operator = "IS NOT NULL";
    }

    $this->query->addCondition($this->options['group'], $field, NULL, $operator);
  }

}
