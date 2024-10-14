<?php

namespace Drupal\views\Plugin\views\argument;

/**
 * Provides an interface for all views arguments that have a formula. operators.
 */
interface ArgumentInterface {

  /**
   * Returns the formula for this argument.
   *
   * @return string
   */
  public function getFormula(): string;

}
