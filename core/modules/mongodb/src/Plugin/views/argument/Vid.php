<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\node\Plugin\views\argument\Vid as NodeVid;

/**
 * Overriding the views argument plugin "node_vid".
 */
class Vid extends NodeVid {

  use NumericArgumentTrait;

}
