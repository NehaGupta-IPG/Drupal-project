<?php

namespace Drupal\mongodb\Plugin\views\filter;

/**
 * Overriding the views filter plugin "in_operator".
 */
trait InOperatorTrait {

  /**
   * The MongoDB field.
   *
   * @var string
   */
  protected $mongodbField;

  /**
   * {@inheritdoc}
   */
  public function query() {
    if ($this->table == $this->view->storage->get('base_table')) {
      $this->mongodbField = $this->realField;
    }
    elseif (!empty($this->relationship)) {
      $this->mongodbField = "$this->relationship.$this->realField";
    }
    else {
      // Throw an exception.
      $this->mongodbField = $this->realField;
    }

    $info = $this->operators();
    if (!empty($info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opSimple() {
    if (empty($this->value)) {
      return;
    }
    $this->ensureMyTable();

    // We use array_values() because the checkboxes keep keys and that can cause
    // array addition problems.
    $this->query->addCondition($this->options['group'], $this->mongodbField, array_values($this->value), $this->operator);
  }

  /**
   * {@inheritdoc}
   */
  protected function opEmpty() {
    $this->ensureMyTable();
    if ($this->operator == 'empty') {
      $operator = "IS NULL";
    }
    else {
      $operator = "IS NOT NULL";
    }

    $this->query->addCondition($this->options['group'], $this->mongodbField, NULL, $operator);
  }

}
