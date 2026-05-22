<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\Date as CoreDate;

// cspell:ignore datedate

/**
 * Overriding the views argument plugin "date".
 */
class Date extends CoreDate {

  use FormulaTrait;

  /**
   * The MongoDB condition operator.
   *
   * @var string
   */
  protected $mongodbOperator = 'DATEDATE';

}
