<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\YearDate as CoreYearDate;

// cspell:ignore datedate

/**
 * Overriding the views argument plugin "date_year".
 */
class YearDate extends CoreYearDate {

  use FormulaTrait;

  /**
   * The MongoDB condition operator.
   *
   * @var string
   */
  protected $mongodbOperator = 'DATEDATE';

}
