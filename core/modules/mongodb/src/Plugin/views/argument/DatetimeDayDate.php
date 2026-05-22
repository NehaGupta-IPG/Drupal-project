<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\datetime\Plugin\views\argument\DayDate;

/**
 * Overriding the views argument plugin "datetime_day".
 */
class DatetimeDayDate extends DayDate {

  use DatetimeDateTrait;

}
