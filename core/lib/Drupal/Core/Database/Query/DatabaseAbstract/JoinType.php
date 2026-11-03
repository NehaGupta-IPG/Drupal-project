<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query\DatabaseAbstract;

/**
 * Enumeration of the join types between tables in SQL.
 */
enum JoinType {

  case BaseTable;
  case Inner;
  case Left;

  // Right outer joins are currently not supported.

}
