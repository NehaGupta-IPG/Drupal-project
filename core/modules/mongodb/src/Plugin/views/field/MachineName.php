<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\MachineName as CoreMachineName;

/**
 * Overriding the views field plugin "machine_name".
 */
class MachineName extends CoreMachineName {

  use FieldPluginTrait;

}
