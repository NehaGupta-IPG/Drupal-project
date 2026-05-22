<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\Dropbutton as CoreDropbutton;

/**
 * Overriding the views field plugin "dropbutton".
 */
class Dropbutton extends CoreDropbutton {

  use FieldPluginTrait;

}
