<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\ColumnSize;
use Drupal\Core\Database\SchemaDefinition\ColumnType;
use Drupal\Core\Database\SchemaDefinition\Differ;
use Drupal\Core\Database\SchemaDefinition\ForeignKey;
use Drupal\Core\Database\SchemaDefinition\GeneratedColumnExpression;
use Drupal\Core\Database\SchemaDefinition\GeneratedColumnStorage;
use Drupal\Core\Database\SchemaDefinition\Index;
use Drupal\Core\Database\SchemaDefinition\IntValue;
use Drupal\Core\Database\SchemaDefinition\KeyColumn;
use Drupal\Core\Database\SchemaDefinition\NullValue;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Schema;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;
use Drupal\Core\Database\SchemaDefinition\StringValue;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\Core\Database\SchemaDefinition\UniqueKey;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests database schema introspection.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class SchemaIntrospectionTest extends DatabaseTestBase {

  /**
   * Tests execution of Schema::introspect().
   */
  public function testSchemaIntrospect(): void {
    $testTableDefinition = new Table(
      name: 'test_introspection',
      description: 'Basic test table for schema introspection.',
      columns: [
        Column::serial(
          name: 'id',
        ),
        Column::varcharAscii(
          name: 'name',
          description: "A person's name",
          length: 255,
          notNull: TRUE,
          default: new StringValue(''),
          binary: TRUE,
        ),
        Column::int(
          name: 'age',
          description: "The person's age",
          size: ColumnSize::Small,
          unsigned: TRUE,
          notNull: TRUE,
          default: new IntValue(0),
        ),
        Column::generated(
          name: 'double_age',
          type: ColumnType::Int,
          description: "The person's age X 2",
          storage: GeneratedColumnStorage::Virtual,
          expression: new GeneratedColumnExpression('[age] * 2'),
          dbSpecificExtra: ['pgsql' => ['pgsql_type' => 'bigint']],
        ),
        Column::varchar(
          name: 'job',
          description: "The person's job",
          length: 255,
          notNull: TRUE,
          default: new StringValue('Undefined'),
        ),
        Column::create(
          name: 'db_timestamp',
          description: "The database timestamp",
          dbSpecificExtra: [
            'mysql' => [
              'mysql_type' => 'timestamp',
            ],
            'pgsql' => [
              'pgsql_type' => 'timestamp',
            ],
            'sqlite' => [
              'sqlite_type' => 'datetime',
            ],
          ],
          notNull: FALSE,
          default: new NullValue(),
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
      dbSpecificExtra: [
        'mysql' => [
          'mysql_engine' => 'InnoDB',
          'mysql_character_set' => 'utf8mb4',
        ],
      ],
    );

    $testSchemaDefinition = new Schema(
      type: SchemaDefinitionType::Test,
      name: 'test_introspection',
      tables: [$testTableDefinition],
    );

    $this->connection->schema()->createSchemaFromDefinition($testSchemaDefinition);

    $introspectedSchema = $this->connection->schema()->introspect(['/^test_introspection$/']);

    $this->assertEquals([], Differ::diff($testSchemaDefinition, $introspectedSchema));
  }

}
