<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\FullDate as CoreFullDate;

// cspell:ignore datedate

/**
 * Overriding the views argument plugin "date_fulldate".
 */
class FullDate extends CoreFullDate {

  use FormulaTrait;

  /**
   * The MongoDB condition operator.
   *
   * @var string
   */
  protected $mongodbOperator = 'DATEDATE';

}
