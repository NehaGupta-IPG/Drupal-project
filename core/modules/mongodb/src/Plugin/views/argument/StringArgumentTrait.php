<?php

namespace Drupal\mongodb\Plugin\views\argument;

/**
 * Trait for overriding the StringArgument class.
 */
trait StringArgumentTrait {

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $argument = $this->argument;
    if (!empty($this->options['transform_dash'])) {
      $argument = strtr($argument, '-', ' ');
    }

    if (!empty($this->options['break_phrase'])) {
      $this->unpackArgumentValue();
    }
    else {
      $this->value = [$argument];
      $this->operator = 'or';
    }

    if (!empty($this->definition['many to one'])) {
      if (!empty($this->options['glossary'])) {
        $this->helper->formula = TRUE;
      }
      $this->helper->ensureMyTable();
      $this->helper->addFilter();
      return;
    }

    $this->ensureMyTable();
    $formula = FALSE;
    if (empty($this->options['glossary'])) {
      if ($this->table == $this->view->storage->get('base_table')) {
        $field = $this->realField;
      }
      else {
        $field = "$this->tableAlias.$this->realField";
      }
    }
    else {
      $formula = TRUE;
      $field = $this->getFormula();
    }

    if (count($this->value) > 1) {
      $operator = 'IN';
      $argument = $this->value;
    }
    else {
      $operator = '=';
    }

    if ($formula) {
      $placeholder = $this->placeholder();
      if ($operator == 'IN') {
        $field .= " IN($placeholder)";
      }
      else {
        $field .= ' = ' . $placeholder;
      }
      $this->query->addSubstringField($this->realField . '_truncated', $field, 0, intval($this->options['limit']));
    }
    else {
      $this->query->addCondition(0, $field, $argument, $operator);
    }
  }

}
