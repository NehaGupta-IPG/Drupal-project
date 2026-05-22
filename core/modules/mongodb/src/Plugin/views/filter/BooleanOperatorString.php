<?php

namespace Drupal\mongodb\Plugin\views\filter;

/**
 * Overriding the views filter plugin "boolean_string".
 */
class BooleanOperatorString extends BooleanOperator {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    if (empty($this->value)) {
      if ($this->accept_null) {
        $condition = $this->query->getConnection()->condition('OR');
        $condition->condition("$this->tableAlias.$this->realField", '', self::EQUAL);
        $condition->isNull("$this->tableAlias.$this->realField");
        $this->query->addCondition($this->options['group'], $condition);
      }
      else {
        $condition = $this->query->getConnection()->condition('AND');
        $condition->condition("$this->tableAlias.$this->realField", '', self::EQUAL);
        $this->query->addCondition($this->options['group'], $condition);
      }
    }
    else {
      $condition = $this->query->getConnection()->condition('AND');
      $condition->condition("$this->tableAlias.$this->realField", '', self::NOT_EQUAL);
      $this->query->addCondition($this->options['group'], $condition);
    }
  }

}
