<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\Markup as CoreMarkup;

/**
 * Overriding the views field plugin "markup".
 */
class Markup extends CoreMarkup {

  use FieldPluginTrait;

}
