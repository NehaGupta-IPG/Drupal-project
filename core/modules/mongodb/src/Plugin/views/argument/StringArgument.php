<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\StringArgument as CoreStringArgument;

/**
 * Overriding the views argument plugin "string".
 */
class StringArgument extends CoreStringArgument {

  use StringArgumentTrait;

}
