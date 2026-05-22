<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\node\Plugin\views\argument\Type as NodeType;

/**
 * Overriding the views argument plugin "node_type".
 */
class Type extends NodeType {

  use StringArgumentTrait;

}
