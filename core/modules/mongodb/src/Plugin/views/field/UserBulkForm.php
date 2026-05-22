<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\user\Plugin\views\field\UserBulkForm as CoreUserBulkForm;

/**
 * Overriding the views field plugin "user_bulk_form".
 */
class UserBulkForm extends CoreUserBulkForm {

  use FieldPluginTrait;

}
