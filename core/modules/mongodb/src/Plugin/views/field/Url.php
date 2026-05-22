<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\Url as CoreUrl;

/**
 * Overriding the views field plugin "url".
 */
class Url extends CoreUrl {

  use FieldPluginTrait;

}
