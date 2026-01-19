<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Enumeration of database object types.
 */
enum DatabaseObjectType: string {

  case Database = 'database';
  case Schema = 'schema';
  case Table = 'table';

}
