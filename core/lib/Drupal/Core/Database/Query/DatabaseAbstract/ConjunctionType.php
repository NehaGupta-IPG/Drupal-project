<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query\DatabaseAbstract;

/**
 * Enumeration of the conjunction types for conditions.
 */
enum ConjunctionType {

  case And;
  case Or;

}
