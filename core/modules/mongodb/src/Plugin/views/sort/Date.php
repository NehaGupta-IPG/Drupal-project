<?php

namespace Drupal\mongodb\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\Date as CoreDate;

/**
 * Overrides the views sort plugin "date".
 */
class Date extends CoreDate {

  use DateTrait;

}
