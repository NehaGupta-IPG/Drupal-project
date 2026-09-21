<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query\DatabaseAbstract;

/**
 * Enumeration of sort orders in SQL.
 */
enum Order {

  case Asc;
  case Desc;

}
