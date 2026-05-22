<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\YearMonthDate as CoreYearMonthDate;

// cspell:ignore datedate

/**
 * Overriding the views argument plugin "date_year_month".
 */
class YearMonthDate extends CoreYearMonthDate {

  use FormulaTrait;

  /**
   * The MongoDB condition operator.
   *
   * @var string
   */
  protected $mongodbOperator = 'DATEDATE';

}
