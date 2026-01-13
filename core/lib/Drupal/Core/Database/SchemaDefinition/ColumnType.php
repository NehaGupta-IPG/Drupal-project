<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Enumeration of cases for column type.
 *
 * It represents the generic data type of a table column. Most types just map
 * to the according database engine specific data types.
 */
enum ColumnType: string {

  // String related types.
  case Char = 'char';
  case Varchar = 'varchar';
  // This is to indicate limiting the accepted characters in the column to the
  // US ASCII subset only.
  case VarcharAscii = 'varchar_ascii';
  case Text = 'text';

  // Number related types.
  case Int = 'int';
  // This is to indicate auto incrementing fields. For example, this will
  // expand to 'INT auto_increment' on MySQL.
  case Serial = 'serial';
  case Float = 'float';
  case Numeric = 'numeric';

  // Other types.
  case Blob = 'blob';

}
