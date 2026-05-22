<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\Combine as CoreCombine;

/**
 * Overriding the views filter plugin "combine".
 */
class Combine extends CoreCombine {

  use FilterPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->view->_build('field');
    $fields = [];
    $integer_fields = [];
    // Only add the fields if they have a proper field and table alias.
    foreach ($this->options['fields'] as $id) {
      // Overridden fields can lead to fields missing from a display that are
      // still set in the non-overridden combined filter.
      if (!isset($this->view->field[$id])) {
        // If fields are no longer available that are needed to filter by, make
        // sure no results are shown to prevent displaying more then intended.
        $this->view->build_info['fail'] = TRUE;
        continue;
      }
      $field = $this->view->field[$id];
      // Always add the table of the selected fields to be sure a table alias
      // exists.
      $field->ensureMyTable();
      if (!empty($field->tableAlias) && !empty($field->realField)) {
        $fields[] = "$field->tableAlias.$field->realField";
      }
      if (isset($field->definition['id']) && ($field->definition['id'] == 'numeric') && (!isset($field->definition['float']) || !$field->definition['float'])  && !empty($field->realField)) {
        $integer_fields[] = '$' . $field->realField;
      }
    }
    if ($fields) {
      $base_table = $this->view->storage->get('base_table') . '.';
      $base_table_length = strlen($base_table);

      $count = count($fields);
      $separated_fields = [];
      foreach ($fields as $key => $field) {
        // MongoDB: Remove the base table name with dot from the field name.
        if (substr($field, 0, $base_table_length) === $base_table) {
          $field = substr($field, $base_table_length);
        }
        // MongoDB: For the $concat operator must the field names start with
        // the dollar sign.
        $separated_fields[] = '$' . $field;

        if ($key < $count - 1) {
          $separated_fields[] = ' ';
        }
      }

      $info = $this->operators();
      if (!empty($info[$this->operator]['method'])) {
        $values = [
          'separated fields' => $separated_fields,
          'integer fields' => $integer_fields,
        ];
        $this->{$info[$this->operator]['method']}($values);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function opEqual($expression) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $placeholder = $this->placeholder();
      $operator = $this->getConditionOperator($this->operator());
      $separated_fields = $expression['separated fields'];

      $this->query->addConcatField($placeholder, $separated_fields);
      $condition = $this->query->getConnection()->condition('AND');
      foreach ($separated_fields as $field) {
        $condition->condition($field, NULL, 'IS NOT NULL');
      }
      $condition->condition($placeholder, $this->connection->escapeLike($this->value), $operator);
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opEqual($expression);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opContains($expression) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $separated_fields = [];
      foreach ($expression['separated fields'] as $separated_field) {
        if (strpos($separated_field, '.') !== FALSE) {
          $placeholder = $this->placeholder();
          $this->query->addConditionField($placeholder, substr($separated_field, 1));
          $separated_fields[] = '$' . $placeholder;
        }
        else {
          $separated_fields[] = $separated_field;
        }
      }

      $placeholder = $this->placeholder();
      $this->query->addConcatField($placeholder, $separated_fields);
      $condition = $this->query->getConnection()->condition('AND');
      $condition->condition($placeholder, '%' . $this->connection->escapeLike($this->value) . '%', 'LIKE');
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opContains($expression);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opContainsWord($expression) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $placeholder = $this->placeholder();

      // Don't filter on empty strings.
      if (empty($this->value)) {
        return;
      }

      // Match all words separated by spaces or sentences encapsulated by double
      // quotes.
      preg_match_all(static::WORDS_PATTERN, ' ' . $this->value, $matches, PREG_SET_ORDER);

      // Switch between the 'word' and 'allwords' operator.
      $type = $this->operator == 'word' ? 'OR' : 'AND';
      $group = $this->query->setWhereGroup($type);
      $operator = $this->getConditionOperator('LIKE');

      if (!empty($matches)) {
        $separated_fields = $expression['separated fields'];
        $this->query->addConcatField($placeholder, $separated_fields, $expression['integer fields']);
        $condition = $this->query->getConnection()->condition($type);
      }

      foreach ($matches as $match) {
        // Clean up the user input and remove the sentence delimiters.
        $word = trim($match[2], ',?!();:-"');
        $condition->condition($placeholder, '%' . $this->connection->escapeLike($word) . '%', $operator);
      }

      if (!empty($matches)) {
        $this->query->addCondition($group, $condition);
      }
    }
    else {
      parent::opContainsWord($expression);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opStartsWith($expression) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $placeholder = $this->placeholder();
      $operator = $this->getConditionOperator('LIKE');
      $separated_fields = $expression['separated fields'];

      $this->query->addConcatField($placeholder, $separated_fields);
      $condition = $this->query->getConnection()->condition('AND');
      $condition->condition($placeholder, $this->connection->escapeLike($this->value) . '%', $operator);
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opStartsWith($expression);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opNotStartsWith($expression) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $placeholder = $this->placeholder();
      $operator = $this->getConditionOperator('NOT LIKE');
      $separated_fields = $expression['separated fields'];

      $this->query->addConcatField($placeholder, $separated_fields);
      $condition = $this->query->getConnection()->condition('AND');
      // The function addConcatField() creates an empty string when there is no
      // value in the concatenated fields.
      $condition->condition($placeholder, '', '<>');
      $condition->condition($placeholder, $this->connection->escapeLike($this->value) . '%', $operator);
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opNotStartsWith($expression);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opEndsWith($expression) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $placeholder = $this->placeholder();
      $operator = $this->getConditionOperator('LIKE');
      $separated_fields = $expression['separated fields'];

      $this->query->addConcatField($placeholder, $separated_fields);
      $condition = $this->query->getConnection()->condition('AND');
      $condition->condition($placeholder, '%' . $this->connection->escapeLike($this->value), $operator);
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opEndsWith($expression);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opNotEndsWith($expression) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $placeholder = $this->placeholder();
      $operator = $this->getConditionOperator('NOT LIKE');
      $separated_fields = $expression['separated fields'];

      $this->query->addConcatField($placeholder, $separated_fields);
      $condition = $this->query->getConnection()->condition('AND');
      // The function addConcatField() creates an empty string when there is no
      // value in the concatenated fields.
      $condition->condition($placeholder, '', '<>');
      $condition->condition($placeholder, '%' . $this->connection->escapeLike($this->value), $operator);
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opNotEndsWith($expression);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opNotLike($expression) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $placeholder = $this->placeholder();
      $operator = $this->getConditionOperator('NOT LIKE');
      $separated_fields = $expression['separated fields'];

      $this->query->addConcatField($placeholder, $separated_fields);
      $condition = $this->query->getConnection()->condition('AND');
      // The function addConcatField() creates an empty string when there is no
      // value in the concatenated fields.
      $condition->condition($placeholder, '', '<>');
      $condition->condition($placeholder, '%' . $this->connection->escapeLike($this->value) . '%', $operator);
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opNotLike($expression);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opRegex($expression) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      $placeholder = $this->placeholder();
      $separated_fields = $expression['separated fields'];

      $this->query->addConcatField($placeholder, $separated_fields);
      $condition = $this->query->getConnection()->condition('AND');
      $condition->condition($placeholder, $this->value, 'REGEXP');
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opRegex($expression);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opEmpty($expression) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      if ($this->operator == 'empty') {
        $operator = "IS NULL";
      }
      else {
        $operator = "IS NOT NULL";
      }

      $separated_fields = $expression['separated fields'];

      $this->query->addConcatField($placeholder, $separated_fields);
      $condition = $this->query->getConnection()->condition('AND');
      // MongoDB: this is not going to work needs testing. Spaces between fields
      // are by definition not null.
      $condition->condition($placeholder, $operator);
      $this->query->addCondition($this->options['group'], $condition);
    }
    else {
      parent::opEmpty($expression);
    }
  }

}
