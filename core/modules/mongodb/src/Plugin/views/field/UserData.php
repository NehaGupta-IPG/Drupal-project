<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\user\Plugin\views\field\UserData as CoreUserData;

/**
 * Overriding the views field plugin "user_data".
 */
class UserData extends CoreUserData {

  use FieldPluginTrait;

}
