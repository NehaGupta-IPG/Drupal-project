<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\GroupByNumeric as CoreGroupByNumeric;

/**
 * Overriding the views filter plugin "groupby_numeric".
 */
class GroupByNumeric extends CoreGroupByNumeric {

  use FilterPluginTrait;

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
  protected function opBetween($field) {
    // @todo Code copied from Numeric::opBetween().
    if (is_numeric($this->value['min']) && is_numeric($this->value['max'])) {
      $operator = $this->operator == 'between' ? 'BETWEEN' : 'NOT BETWEEN';
      $this->query->addCondition($this->options['group'], $field, [$this->value['min'], $this->value['max']], $operator);
    }
    elseif (is_numeric($this->value['min'])) {
      $operator = $this->operator == 'between' ? '>=' : '<';
      $this->query->addCondition($this->options['group'], $field, $this->value['min'], $operator);
    }
    elseif (is_numeric($this->value['max'])) {
      $operator = $this->operator == 'between' ? '<=' : '>';
      $this->query->addCondition($this->options['group'], $field, $this->value['max'], $operator);
    }

    // $placeholder_min = $this->placeholder();
    // $placeholder_max = $this->placeholder();
    // if ($this->operator == 'between') {
    // $this->query->addHavingExpression($this->options['group'], "$field >= $placeholder_min", [$placeholder_min => $this->value['min']]);
    // $this->query->addHavingExpression($this->options['group'], "$field <= $placeholder_max", [$placeholder_max => $this->value['max']]);
    // }
    // else {
    // $this->query->addHavingExpression($this->options['group'], "$field < $placeholder_min OR $field > $placeholder_max", [$placeholder_min => $this->value['min'], $placeholder_max => $this->value['max']]);
    // }
  }

  /**
   * {@inheritdoc}
   */
  protected function opSimple($field) {
    // @todo Code copied from Numeric::opBetween().
    $this->query->addCondition($this->options['group'], $field, $this->value['value'], $this->operator);

    // $placeholder = $this->placeholder();
    // $this->query->addHavingExpression($this->options['group'], "$field $this->operator $placeholder", [$placeholder => $this->value['value']]);
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

    // @todo Code copied from Numeric::opBetween().
    $this->query->addCondition($this->options['group'], $field, NULL, $operator);
    // $this->query->addHavingExpression($this->options['group'], "$field $operator");
  }

}
