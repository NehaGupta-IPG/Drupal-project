<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\LanguageField as CoreLanguageField;

/**
 * Overriding the views field plugin "language".
 */
class LanguageField extends CoreLanguageField {

  use FieldPluginTrait;

}
