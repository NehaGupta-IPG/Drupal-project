<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\options\Plugin\views\argument\StringListField as CoreStringListField;

/**
 * Overriding the views argument plugin "string_list_field".
 */
class StringListField extends CoreStringListField {

  use StringArgumentTrait;

}
