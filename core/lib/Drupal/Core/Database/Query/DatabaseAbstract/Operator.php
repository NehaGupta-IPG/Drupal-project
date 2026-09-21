<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query\DatabaseAbstract;

/**
 * Enumeration of condition operators in SQL.
 */
enum Operator: string {

  case Equal = '=';
  case NotEqual = '<>';

  case Greater = '>';
  case GreaterOrEqual = '>=';

  case Less = '<';
  case LessOrEqual = '<=';

  case IsNull = 'IS NULL';
  case IsNotNull = 'IS NOT NULL';

  case In = 'IN';
  case NotIn = 'NOT IN';

  case Between = 'BETWEEN';
  case NotBetween = 'NOT BETWEEN';

  case Exists = 'EXISTS';
  case NotExists = 'NOT EXISTS';

}
