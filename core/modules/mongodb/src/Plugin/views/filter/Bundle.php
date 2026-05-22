<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\Bundle as CoreBundle;

/**
 * Overriding the views filter plugin "bundle".
 */
class Bundle extends CoreBundle {

  use InOperatorTrait;

  /**
   * {@inheritdoc}
   */
  protected function opSimple() {
    if (empty($this->value)) {
      return;
    }
    $this->ensureMyTable();

    // Bundle names are always strings. Make sure that they are strings.
    if (strtoupper($this->operator) == 'IN') {
      $this->operator = 'IN_STRING';
    }

    // We use array_values() because the checkboxes keep keys and that can cause
    // array addition problems.
    $this->query->addCondition($this->options['group'], $this->mongodbField, array_values($this->value), $this->operator);
  }

}
