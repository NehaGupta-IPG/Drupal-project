<?php

namespace Drupal\mongodb\Plugin\views\argument;

/**
 * Overriding the views argument plugin "formula".
 */
trait FormulaTrait {

  /**
   * {@inheritdoc}
   */
  protected function summaryQuery() {
    $this->ensureMyTable();

    $this->query->addDateDateFormattedField($this->field, $this->realField, $this->getDateFormat($this->argFormat));
    $this->base_alias = $this->name_alias = $this->query->addField(NULL, $this->realField, $this->field);

    return $this->summaryBasics(FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();

    if ($this->relationship) {
      $field = "$this->tableAlias.$this->realField";
    }
    else {
      $field = $this->realField;
    }

    $values = [
      'format' => $this->getDateFormat($this->argFormat),
      'value' => $this->argument,
      'timezone' => $this->query->setupTimezone(),
    ];

    $this->query->addCondition(0, $field, $values, $this->mongodbOperator);
  }

}
