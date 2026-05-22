<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\node\Plugin\views\argument\Nid as NodeNid;

/**
 * Overriding the views argument plugin "node_nid".
 */
class Nid extends NodeNid {

  use NumericArgumentTrait;

}
