<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\BooleanOperator as CoreBooleanOperator;

/**
 * Overriding the views filter plugin "boolean".
 */
class BooleanOperator extends CoreBooleanOperator {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    if ($this->view->getDatabaseDriver() == 'mongodb' && $this->table == $this->view->storage->get('base_table')) {
      $field = $this->realField;
    }
    else {
      $field = "$this->tableAlias.$this->realField";
    }

    $info = $this->operators();
    if (!empty($info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}($field, $info[$this->operator]['query_operator'] ?? NULL);
    }
  }

  /**
   * Adds a where condition to the query for a boolean value.
   *
   * @param string $field
   *   The field name to add the where condition for.
   * @param string $query_operator
   *   (optional) Either self::EQUAL or self::NOT_EQUAL. Defaults to
   *   self::EQUAL.
   */
  protected function queryOpBoolean($field, $query_operator = self::EQUAL) {
    if ($this->view->getDatabaseDriver() == 'mongodb') {
      if (empty($this->value)) {
        if ($this->accept_null) {
          if ($query_operator === self::EQUAL) {
            $condition = ($this->query->getConnection()->condition('OR'))
              ->condition($field, FALSE, $query_operator)
              ->isNull($field);
          }
          else {
            $condition = ($this->query->getConnection()->condition('AND'))
              ->condition($field, FALSE, $query_operator)
              ->isNotNull($field);
          }
          $this->query->addCondition($this->options['group'], $condition);
        }
        else {
          $this->query->addCondition($this->options['group'], $field, FALSE, $query_operator);
        }
      }
      else {
        if (!empty($this->definition['use_equal'])) {
          // Forces a self::EQUAL operator instead of a self::NOT_EQUAL for
          // performance reasons.
          if ($query_operator === self::EQUAL) {
            $this->query->addCondition($this->options['group'], $field, TRUE, self::EQUAL);
          }
          else {
            $this->query->addCondition($this->options['group'], $field, FALSE, self::EQUAL);
          }
        }
        else {
          $this->query->addCondition($this->options['group'], $field, TRUE, $query_operator);
        }
      }
    }
    else {
      parent::queryOpBoolean($field, $query_operator);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opEmpty(string $field): void {
    if ($this->operator === 'empty') {
      $this->query->addCondition($this->options['group'], $field, FALSE);
    }
    else {
      $this->query->addCondition($this->options['group'], $field, TRUE);
    }
  }

}
