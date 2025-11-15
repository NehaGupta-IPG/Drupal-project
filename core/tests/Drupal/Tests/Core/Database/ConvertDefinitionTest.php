<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\ColumnSize;
use Drupal\Core\Database\SchemaDefinition\ColumnType;
use Drupal\Core\Database\SchemaDefinition\ConvertDefinition;
use Drupal\Core\Database\SchemaDefinition\ForeignKey;
use Drupal\Core\Database\SchemaDefinition\Index;
use Drupal\Core\Database\SchemaDefinition\KeyColumn;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\Core\Database\SchemaDefinition\UniqueKey;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests conversion of SchemaDefinition objects to legacy array-based structure.
 */
#[Group('Database')]
class ConvertDefinitionTest extends UnitTestCase {

  /**
   * Tests ConvertDefinition::tableToArray.
   */
  public function testConvertDefinition(): void {
    $arraySpecification = [
      'description' => 'Basic test table for the database unit tests.',
      'fields' => [
        'id' => [
          'type' => 'serial',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'name' => [
          'description' => "A person's name",
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
          'binary' => TRUE,
        ],
        'age' => [
          'description' => "The person's age",
          'type' => 'int',
          'size' => 'small',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
        'job' => [
          'description' => "The person's job",
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
          'default' => 'Undefined',
        ],
        'db_timestamp' => [
          'description' => "The database timestamp",
          'mysql_type' => 'timestamp',
          'pgsql_type' => 'timestamp',
          'sqlite_type' => 'datetime',
          'not null' => FALSE,
          'default' => NULL,
        ],
      ],
      'primary key' => ['id'],
      'unique keys' => [
        'name' => ['name'],
      ],
      'indexes' => [
        'ages' => ['age'],
        'age_job_prefix' => ['age', ['job', 50]],
        'age_name_prefix' => ['age', ['name', 20]],
        'foo' => ['job'],
      ],
      'indexes extra' => [
        'foo' => [
          'pgsql' => [
            'type' => 'gist',
            'operator' => 'gist_trgm_ops',
          ],
          'time_warp' => [
            'jump' => 'to_the_left',
            'step' => 'to_the_right',
          ],
        ],
      ],
      'foreign keys' => [
        'user_id' => [
          'table' => 'test_users',
          'columns' => ['id' => 'id'],
        ],
      ],
    ];

    $schemaDefinition = new Table(
      name: 'test',
      description: 'Basic test table for the database unit tests.',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Serial,
          unsigned: TRUE,
          notNull: TRUE,
        ),
        new Column(
          name: 'name',
          description: "A person's name",
          type: ColumnType::VarcharAscii,
          length: 255,
          notNull: TRUE,
          default: '',
          binary: TRUE,
        ),
        new Column(
          name: 'age',
          description: "The person's age",
          type: ColumnType::Int,
          size: ColumnSize::Small,
          unsigned: TRUE,
          notNull: TRUE,
          default: 0,
        ),
        new Column(
          name: 'job',
          description: "The person's job",
          type: ColumnType::Varchar,
          length: 255,
          notNull: TRUE,
          default: 'Undefined',
        ),
        new Column(
          name: 'db_timestamp',
          description: "The database timestamp",
          dbSpecificType: [
            'mysql' => 'timestamp',
            'pgsql' => 'timestamp',
            'sqlite' => 'datetime',
          ],
          notNull: FALSE,
          default: NULL,
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
      uniqueKeys: [
        new UniqueKey(
          name: 'name',
          columns: ['name'],
        ),
      ],
      indexes: [
        new Index(
          name: 'ages',
          columns: ['age'],
        ),
        new Index(
          name: 'age_job_prefix',
          columns: ['age', ['job', 50]],
        ),
        new Index(
          name: 'age_name_prefix',
          columns: ['age', new KeyColumn(name: 'name', length: 20)],
        ),
        new Index(
          name: 'foo',
          columns: ['job'],
          dbSpecificExtra: [
            'pgsql' => [
              'type' => 'gist',
              'operator' => 'gist_trgm_ops',
            ],
            'time_warp' => [
              'jump' => 'to_the_left',
              'step' => 'to_the_right',
            ],
          ],
        ),
      ],
      foreignKeys: [
        new ForeignKey(
          name: 'user_id',
          foreignTable: 'test_users',
          columns: ['id'],
          foreignColumns: ['id'],
        ),
      ],
    );

    $this->assertEquals($arraySpecification, ConvertDefinition::tableToArray($schemaDefinition));
  }

}
