<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\datetime\Plugin\views\argument\YearDate;

/**
 * Overriding the views argument plugin "datetime_year".
 */
class DatetimeYearDate extends YearDate {

  use DatetimeDateTrait;

}
