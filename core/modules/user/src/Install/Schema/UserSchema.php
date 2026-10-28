<?php

declare(strict_types=1);

namespace Drupal\user\Install\Schema;

use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\ColumnSize;
use Drupal\Core\Database\SchemaDefinition\ForeignKey;
use Drupal\Core\Database\SchemaDefinition\Index;
use Drupal\Core\Database\SchemaDefinition\IntValue;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Schema;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;
use Drupal\Core\Database\SchemaDefinition\StringValue;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\Core\Extension\Install\SchemaInterface;

/**
 * Database Schema for User module.
 */
class UserRemovedUpdate implements SchemaInterface {

  /**
   * Implements hook_schema().
   */
  public function getSchema(): Schema {
    $tables[] = new Table(
      name: 'users_data',
      description: 'Stores module data as key/value pairs per user.',
      columns: [
        Column::int(
          name: 'uid',
          description: 'The {users}.uid this record affects.',
          unsigned: TRUE,
          notNull: TRUE,
          default: new IntValue(0),
        ),
        Column::varcharAscii(
          name: 'module',
          description: 'The name of the module declaring the variable.',
          length: DRUPAL_EXTENSION_NAME_MAX_LENGTH,
          notNull: TRUE,
          default: new StringValue(''),
        ),
        Column::varcharAscii(
          name: 'name',
          description: 'The identifier of the data.',
          length: 128,
          notNull: TRUE,
          default: new StringValue(''),
        ),
        Column::blob(
          name: 'value',
          description: 'The value.',
          size: ColumnSize::Big,
          notNull: FALSE,
        ),
        Column::int(
          name: 'serialized',
          description: 'Whether value is serialized.',
          size: ColumnSize::Tiny,
          unsigned: TRUE,
          default: new IntValue(0),
        ),
      ],
      primaryKey: new PrimaryKey(['uid', 'module', 'name']),
      indexes: [
        new Index(name: 'module', columns: ['module']),
        new Index(name: 'name', columns: ['name']),
      ],
      foreignKeys: [
        new ForeignKey(
          name: 'data_user',
          foreignTable: 'users',
          columns: ['uid'],
          foreignColumns: ['uid'],
        ),
      ],
    );

    return new Schema(
      type: SchemaDefinitionType::Module,
      name: 'user',
      tables: $tables,
    );
  }

}
