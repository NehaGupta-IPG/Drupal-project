<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\DayDate as CoreDayDate;

// cspell:ignore datedate

/**
 * Overriding the views argument plugin "date_day".
 */
class DayDate extends CoreDayDate {

  use FormulaTrait;

  /**
   * The MongoDB condition operator.
   *
   * @var string
   */
  protected $mongodbOperator = 'DATEDATE';

}
