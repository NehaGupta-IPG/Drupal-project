<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\StringFilter as CoreStringFilter;

/**
 * Overrides the views filter plugin "string".
 */
class StringFilter extends CoreStringFilter {

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

    $info = $this->operators();
    if (!empty($info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function opEqual($field) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $condition = $this->query->getConnection()->condition('AND');
      $condition->condition($field, NULL, 'IS NOT NULL');
      $condition->condition($field, $this->value, $this->operator());
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opEqual($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opContains($field) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $pattern = '%' . $this->connection->escapeLike($this->value) . '%';
      $operator = $this->getConditionOperator('LIKE');
      if (strpos($field, '.') !== FALSE) {
        $placeholder = $this->placeholder();
        $this->query->addConditionField($placeholder, $field);
        $field = $placeholder;
      }
      $condition = $this->query->getConnection()->condition('AND');
      $condition->condition($field, NULL, 'IS NOT NULL');
      $condition->condition($field, $pattern, $operator);
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opContains($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opContainsWord($field) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $condition = $this->operator == 'word' ? $this->query->getConnection()->condition('OR') : $this->query->getConnection()->condition('AND');

      // Don't filter on empty strings.
      if (empty($this->value)) {
        return;
      }

      preg_match_all(static::WORDS_PATTERN, ' ' . $this->value, $matches, PREG_SET_ORDER);
      $operator = $this->getConditionOperator('LIKE');
      foreach ($matches as $match) {
        $phrase = FALSE;
        // Strip off phrase quotes.
        if ($match[2][0] == '"') {
          $match[2] = substr($match[2], 1, -1);
          $phrase = TRUE;
        }
        $words = trim($match[2], ',?!();:-');
        $words = $phrase ? [$words] : preg_split('/ /', $words, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $word) {
          $condition->condition($field, '%' . $this->connection->escapeLike(trim($word, " ,!?")) . '%', $operator);
        }
      }

      if ($condition->count() === 0) {
        return;
      }

      // Previously this was a call_user_func_array but that's unnecessary
      // as views will unpack an array that is a single arg.
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opContainsWord($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opStartsWith($field) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $pattern = $this->connection->escapeLike($this->value) . '%';
      $operator = $this->getConditionOperator('LIKE');
      $condition = $this->query->getConnection()->condition('AND');
      $condition->condition($field, NULL, 'IS NOT NULL');
      $condition->condition($field, $pattern, $operator);
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opStartsWith($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opNotStartsWith($field) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $pattern = $this->connection->escapeLike($this->value) . '%';
      $operator = $this->getConditionOperator('NOT LIKE');
      $condition = $this->query->getConnection()->condition('AND');
      $condition->condition($field, NULL, 'IS NOT NULL');
      $condition->condition($field, $pattern, $operator);
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opNotStartsWith($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opEndsWith($field) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $pattern = '%' . $this->connection->escapeLike($this->value);
      $operator = $this->getConditionOperator('LIKE');
      $condition = $this->query->getConnection()->condition('AND');
      $condition->condition($field, NULL, 'IS NOT NULL');
      $condition->condition($field, $pattern, $operator);
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opEndsWith($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opNotEndsWith($field) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $pattern = '%' . $this->connection->escapeLike($this->value);
      $operator = $this->getConditionOperator('NOT LIKE');
      $condition = $this->query->getConnection()->condition('AND');
      $condition->condition($field, NULL, 'IS NOT NULL');
      $condition->condition($field, $pattern, $operator);
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opNotEndsWith($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opNotLike($field) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $pattern = '%' . $this->connection->escapeLike($this->value) . '%';
      $operator = $this->getConditionOperator('NOT LIKE');
      $condition = $this->query->getConnection()->condition('AND');
      $condition->condition($field, NULL, 'IS NOT NULL');
      $condition->condition($field, $pattern, $operator);
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opNotLike($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opShorterThan($field) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $placeholder = $this->placeholder() . '_shorter_then';
      $this->query->addFieldLength($placeholder, '$' . $field);
      $condition = $this->query->getConnection()->condition('AND');
      $condition->condition($placeholder, $this->value, '<');
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opShorterThan($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opLongerThan($field) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $placeholder = $this->placeholder() . '_longer_then';
      $this->query->addFieldLength($placeholder, '$' . $field);
      $condition = $this->query->getConnection()->condition('AND');
      $condition->condition($placeholder, $this->value, '>');
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opLongerThan($field);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opRegex($field) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $condition = $this->query->getConnection()->condition('AND');
      $condition->condition($field, NULL, 'IS NOT NULL');
      $condition->condition($field, $this->value, 'REGEXP');
      $this->query->addCondition($this->options['group'], $condition);
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
      $condition = $this->query->getConnection()->condition('AND');
      $condition->condition($field, NULL, 'IS NOT NULL');
      $condition->condition($field, $this->value, 'NOT REGEXP');
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opNotRegex($field);
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

}
