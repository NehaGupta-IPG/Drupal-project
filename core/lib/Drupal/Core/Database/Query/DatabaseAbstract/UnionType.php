<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query\DatabaseAbstract;

/**
 * Enumeration of the union types between tables in SQL.
 */
enum UnionType {

  case Distinct;
  case All;

}
