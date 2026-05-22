<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\NumericFilter as CoreNumericFilter;

/**
 * Overriding the views filter plugin "numeric".
 */
class NumericFilter extends CoreNumericFilter {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $field = $this->realField;

    if ($this->view->getDatabaseDriver() == 'mongodb') {
      if (!empty($this->query->getCurrentRevisionTable()) && !empty($this->query->getAllRevisionsTable())) {
        $search_needle = $this->query->getCurrentRevisionTable() . '.';
        $replace_needle = $this->query->getAllRevisionsTable() . '.';
        $field = str_replace($search_needle, $replace_needle, $field);
      }
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
    if ($this->view->getDatabaseDriver() == 'mongodb') {
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
    }
    else {
      parent::opBetween($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opSimple($field) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $this->query->addCondition($this->options['group'], $field, $this->value['value'], $this->operator);
    }
    else {
      parent::opSimple($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opEmpty($field) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      if ($this->operator == 'empty') {
        $operator = "IS NULL";
      }
      else {
        $operator = "IS NOT NULL";
      }

      $this->query->addCondition($this->options['group'], $field, NULL, $operator);
    }
    else {
      parent::opEmpty($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opRegex($field) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $placeholder = $this->placeholder();

      $integer_fields = [];
      if (isset($this->options['field'])) {
        $view_field = $this->view->field[$this->options['field']];
        if (isset($view_field->definition['id']) && ($view_field->definition['id'] == 'numeric') && (!isset($view_field->definition['float']) || !$view_field->definition['float'])  && !empty($view_field->realField)) {
          $integer_fields = ['$' . $field];
        }
      }
      $this->query->addConcatField($placeholder, ['$' . $field], $integer_fields);
      $this->query->addCondition($this->options['group'], $placeholder, $this->value['value'], 'REGEXP');
    }
    else {
      parent::opRegex($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opNotRegex($field) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $placeholder = $this->placeholder();

      $integer_fields = [];
      if (isset($this->options['field'])) {
        $view_field = $this->view->field[$this->options['field']];
        if (isset($view_field->definition['id']) && ($view_field->definition['id'] == 'numeric') && (!isset($view_field->definition['float']) || !$view_field->definition['float'])  && !empty($view_field->realField)) {
          $integer_fields = ['$' . $field];
        }
      }
      $this->query->addConcatField($placeholder, ['$' . $field], $integer_fields);
      $this->query->addCondition($this->options['group'], $placeholder, $this->value['value'], 'NOT REGEXP');
    }
    else {
      parent::opNotRegex($field);
    }
  }

}
