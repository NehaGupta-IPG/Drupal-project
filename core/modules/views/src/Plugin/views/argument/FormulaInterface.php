<?php

namespace Drupal\views\Plugin\views\argument;

/**
 * Provides an interface for all Views arguments that can use a formula.
 */
interface FormulaInterface {

  /**
   * Returns the formula for this argument.
   */
  public function getFormula(): string;

}
