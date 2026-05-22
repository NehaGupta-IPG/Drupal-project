<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\user\Plugin\views\filter\Name;

/**
 * Overriding the views filter plugin "user_name".
 */
class UserName extends Name {

  use InOperatorTrait;

}
