<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query\DatabaseAbstract;

/**
 * Enumeration of the conjunction types for conditions.
 */
enum Conjunction {

  case And;
  case Or;

}
