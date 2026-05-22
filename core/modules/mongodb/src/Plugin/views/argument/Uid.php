<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\user\Plugin\views\argument\Uid as CoreUid;

/**
 * Overriding the views argument plugin "user_uid".
 */
class Uid extends CoreUid {

  use NumericArgumentTrait;

}
