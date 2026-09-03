<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\Schema;
use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\ColumnSize;
use Drupal\Core\Database\SchemaDefinition\Index;
use Drupal\Core\Database\SchemaDefinition\IntValue;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Schema as SchemaDefinition;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\Core\Database\SchemaDefinition\UniqueKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests schema operations involving the SchemaDefinition API.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class SchemaOperationsFromDefinitionTest extends DatabaseTestBase {

  /**
   * Database schema instance.
   */
  protected Schema $schema;

  /**
   * Unique key name resolver.
   */
  protected \ReflectionMethod $uniqueKeyNameResolver;

  /**
   * Index name resolver.
   */
  protected \ReflectionMethod $indexNameResolver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->schema = $this->connection->schema();
    $this->uniqueKeyNameResolver = new \ReflectionMethod($this->schema, 'resolveUniqueKeyIdentifierFromDefinition');
    $this->indexNameResolver = new \ReflectionMethod($this->schema, 'resolveIndexIdentifierFromDefinition');
  }

  /**
   * Returns the machine identifier of a unique key.
   *
   * @param \Drupal\Core\Database\SchemaDefinition\Schema $schema
   *   The Schema definition where the table is defined.
   * @param string $tableName
   *   Name of the table.
   * @param string $uniqueKeyName
   *   The name of the unique key as defined in the schema definition.
   */
  protected function getUniqueKeyName(SchemaDefinition $schema, string $tableName, string $uniqueKeyName): string {
    return $this->uniqueKeyNameResolver->invoke($this->schema, $schema, $tableName, $uniqueKeyName);
  }

  /**
   * Returns the machine identifier of an index.
   *
   * @param \Drupal\Core\Database\SchemaDefinition\Schema $schema
   *   The Schema definition where the table is defined.
   * @param string $tableName
   *   Name of the table.
   * @param string $indexName
   *   The name of the index as defined in the schema definition.
   */
  protected function getIndexName(SchemaDefinition $schema, string $tableName, string $indexName): string {
    return $this->indexNameResolver->invoke($this->schema, $schema, $tableName, $indexName);
  }

  /**
   * Tests creating a table from a definition with specialized indexes.
   *
   * By default, drivers only support regular indexes: GIN and GiST index
   * definitions are skipped, and alternative indexes fall back to their
   * regular index definition. Drivers supporting specialized indexes
   * override this method.
   */
  public function testCreateTable(): void {
    $tableName = 'test_from_definition';
    $table = new Table(
      name: $tableName,
      description: 'Test table created from definition.',
      columns: [
        Column::serial(
          name: 'id',
        ),
        Column::int(
          name: 'age',
          notNull: TRUE,
          default: new IntValue(0),
        ),
        Column::varchar(
          name: 'name',
          length: 255,
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
      uniqueKeys: [
        new UniqueKey(name: 'ages', columns: ['age']),
      ],
      indexes: [
        new Index(name: 'names_by_age', columns: ['name', 'age']),
      ],
    );

    $schema = new SchemaDefinition(
      type: SchemaDefinitionType::Test,
      name: $tableName,
      tables: [$table],
    );

    $this->schema->createSchemaFromDefinition($schema);

    $this->assertTrue($this->schema->tableExists($tableName));
    $this->assertTrue($this->schema->uniqueKeyExists($tableName, 'ages'));
    $this->assertTrue($this->schema->indexExists($tableName, 'names_by_age'));
  }

  /**
   * Initial schema for ::testAddTableColumn.
   *
   * @return \Drupal\Core\Database\SchemaDefinition\Schema
   *   The schema.
   */
  protected function initialSchemaForAddTableColumn(string $tableName): SchemaDefinition {
    $table = new Table(
      name: $tableName,
      description: 'Test table created from definition for column addition.',
      columns: [
        Column::serial(
          name: 'id',
        ),
        Column::varchar(
          name: 'name',
          length: 255,
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );

    return new SchemaDefinition(
      type: SchemaDefinitionType::Test,
      name: $tableName,
      tables: [$table],
    );
  }

  /**
   * Target schema for ::testAddTableColumn.
   *
   * @return \Drupal\Core\Database\SchemaDefinition\Schema
   *   The schema.
   */
  protected function targetSchemaForTestAddTableColumn(string $tableName): SchemaDefinition {
    $table = new Table(
      name: $tableName,
      description: 'Test table created from definition for column addition.',
      columns: [
        Column::serial(
          name: 'id',
        ),
        Column::int(
          name: 'age',
          notNull: TRUE,
          default: new IntValue(0),
        ),
        Column::varchar(
          name: 'name',
          length: 255,
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
      uniqueKeys: [
        new UniqueKey(name: 'ages', columns: ['age']),
      ],
      indexes: [
        new Index(name: 'names_by_age', columns: ['name', 'age']),
      ],
    );

    return new SchemaDefinition(
      type: SchemaDefinitionType::Test,
      name: $tableName,
      tables: [$table],
    );
  }

  /**
   * Tests adding a column with specialized indexes.
   *
   * The $keys_new argument of addField() may contain index definition
   * objects. By default, drivers only support regular indexes: GIN and GiST
   * index definitions are skipped, and alternative indexes fall back to
   * their regular index definition. Drivers supporting specialized indexes
   * override this method.
   */
  public function testAddTableColumn(): void {
    $tableName = 'test_add_table_column_from_definition';
    $initalSchema = $this->initialSchemaForAddTableColumn($tableName);
    $this->schema->createSchemaFromDefinition($initalSchema);

    $this->assertTrue($this->schema->tableExists($tableName));
    $this->assertFalse($this->schema->fieldExists($tableName, 'age'));
    $method = new \ReflectionMethod($this->schema, 'findPrimaryKeyColumns');
    $this->assertEquals(['id'], $method->invoke($this->schema, $tableName));
    $this->assertFalse($this->schema->uniqueKeyExists($tableName, 'ages'));
    $this->assertFalse($this->schema->indexExists($tableName, 'names_by_age'));

    $targetSchema = $this->targetSchemaForTestAddTableColumn($tableName);
    $this->schema->addTableColumnFromDefinition(
      targetSchema: $targetSchema,
      tableName: $tableName,
      targetColumnName: 'age',
    );

    $this->assertTrue($this->schema->tableExists($tableName));
    $this->assertTrue($this->schema->fieldExists($tableName, 'age'));
    $method = new \ReflectionMethod($this->schema, 'findPrimaryKeyColumns');
    $this->assertEquals(['id'], $method->invoke($this->schema, $tableName));
    $this->assertTrue($this->schema->uniqueKeyExists($tableName, 'ages'));
    $this->assertTrue($this->schema->indexExists($tableName, 'names_by_age'));
  }

  /**
   * Initial schema for ::testChangeTableColumn.
   *
   * @return \Drupal\Core\Database\SchemaDefinition\Schema
   *   The schema.
   */
  protected function initialSchemaForTestChangeTableColumn(string $tableName): SchemaDefinition {
    $table = new Table(
      name: $tableName,
      description: 'Test table created from definition for column change.',
      columns: [
        Column::serial(
          name: 'id',
        ),
        Column::int(
          name: 'age',
          notNull: TRUE,
          default: new IntValue(0),
        ),
        Column::varchar(
          name: 'name',
          length: 255,
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );

    return new SchemaDefinition(
      type: SchemaDefinitionType::Test,
      name: $tableName,
      tables: [$table],
    );
  }

  /**
   * Target schema for ::testChangeTableColumn.
   *
   * @return \Drupal\Core\Database\SchemaDefinition\Schema
   *   The schema.
   */
  protected function targetSchemaForTestChangeTableColumn(string $tableName): SchemaDefinition {
    $table = new Table(
      name: $tableName,
      description: 'Test table created from definition for column change.',
      columns: [
        Column::serial(
          name: 'id',
        ),
        Column::int(
          name: 'age_new',
          notNull: TRUE,
          default: new IntValue(1),
        ),
        Column::varchar(
          name: 'name',
          length: 255,
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
      uniqueKeys: [
        new UniqueKey(name: 'ages_new', columns: ['age_new']),
      ],
      indexes: [
        new Index(name: 'names_by_age_new', columns: ['name', 'age_new']),
      ],
    );

    return new SchemaDefinition(
      type: SchemaDefinitionType::Test,
      name: $tableName,
      tables: [$table],
    );
  }

  /**
   * Tests changing a column with specialized indexes.
   */
  public function testChangeTableColumn(): void {
    $tableName = 'test_change_table_column_from_definition';
    $initalSchema = $this->initialSchemaForTestChangeTableColumn($tableName);
    $this->schema->createSchemaFromDefinition($initalSchema);

    $this->assertTrue($this->schema->tableExists($tableName));
    $this->assertTrue($this->schema->fieldExists($tableName, 'age'));
    $this->assertFalse($this->schema->fieldExists($tableName, 'age_new'));
    $method = new \ReflectionMethod($this->schema, 'findPrimaryKeyColumns');
    $this->assertEquals(['id'], $method->invoke($this->schema, $tableName));
    $this->assertFalse($this->schema->uniqueKeyExists($tableName, 'ages_new'));
    $this->assertFalse($this->schema->indexExists($tableName, 'names_by_age_new'));

    $targetSchema = $this->targetSchemaForTestChangeTableColumn($tableName);
    $this->schema->changeTableColumnFromDefinition(
      targetSchema: $targetSchema,
      tableName: $tableName,
      currentColumnName: 'age',
      targetColumnName: 'age_new',
    );

    $this->assertTrue($this->schema->tableExists($tableName));
    $this->assertFalse($this->schema->fieldExists($tableName, 'age'));
    $this->assertTrue($this->schema->fieldExists($tableName, 'age_new'));
    $method = new \ReflectionMethod($this->schema, 'findPrimaryKeyColumns');
    $this->assertEquals(['id'], $method->invoke($this->schema, $tableName));
    $this->assertTrue($this->schema->uniqueKeyExists($tableName, 'ages_new'));
    $this->assertTrue($this->schema->indexExists($tableName, 'names_by_age_new'));
  }

  /**
   * Tests changing a column length via schema definition.
   */
  public function testChangeSerialColumnLength(): void {
    $initialSchema = new SchemaDefinition(
      type: SchemaDefinitionType::Test,
      name: 'change_serial_to_big',
      tables: [
        new Table(
          name: 'change_serial_to_big',
          columns: [
            Column::serial(
              name: 'id',
              description: 'Primary Key: Unique ID.',
            ),
            Column::text(
              name: 'text',
              description: 'A text column',
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
      ],
    );
    $this->schema->createSchemaFromDefinition($initialSchema);

    // Increase the size of the column.
    $targetSchema = new SchemaDefinition(
      type: SchemaDefinitionType::Test,
      name: 'change_serial_to_big',
      tables: [
        new Table(
          name: 'change_serial_to_big',
          columns: [
            Column::serial(
              name: 'id',
              description: 'Primary Key: Unique ID.',
              size: ColumnSize::Big,
            ),
            Column::text(
              name: 'text',
              description: 'A text column',
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
      ],
    );
    $this->schema->changeTableColumnFromDefinition(
      targetSchema: $targetSchema,
      tableName: 'change_serial_to_big',
      targetColumnName: 'id',
    );

    $this->assertTrue($this->schema->fieldExists('change_serial_to_big', 'id'));

    // Test if we can actually add a big int.
    $id = $this->connection->insert('change_serial_to_big')->fields([
      'id' => 21474836470,
    ])->execute();

    $id_two = $this->connection->insert('change_serial_to_big')->fields([
      'text' => 'Testing for ID generation',
    ])->execute();

    $this->assertEquals($id + 1, $id_two);
  }

  /**
   * Tests various schema changes' effect on the table's primary key.
   *
   * @param array $initial_primary_key
   *   The initial primary key of the test table.
   * @param array $renamed_primary_key
   *   The primary key of the test table after renaming the test column.
   */
  #[DataProvider('providerTestChangePrimaryKey')]
  public function testChangePrimaryKey(array $initial_primary_key, array $renamed_primary_key): void {
    $table_name = 'test_table';
    $find_primary_key_columns = new \ReflectionMethod(get_class($this->schema), 'findPrimaryKeyColumns');

    // Test making the column the primary key of the table upon creation.
    $table = new Table(
      name: $table_name,
      description: 'Test table created from definition.',
      columns: [
        Column::int(
          name: 'test_column',
          notNull: TRUE,
        ),
        Column::int(
          name: 'other_test_column',
          notNull: TRUE,
        ),
      ],
      primaryKey: new PrimaryKey($initial_primary_key),
    );
    $schema = new SchemaDefinition(
      type: SchemaDefinitionType::Test,
      name: $table_name,
      tables: [$table],
    );

    $this->schema->createSchemaFromDefinition($schema);
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_column'));
    $this->assertEquals($initial_primary_key, $find_primary_key_columns->invoke($this->schema, $table_name));

    // Change the column type and make sure the primary key stays in place.
    $table = new Table(
      name: $table_name,
      description: 'Test table created from definition.',
      columns: [
        Column::varchar(
          name: 'test_column',
          length: 32,
          notNull: TRUE,
        ),
        Column::int(
          name: 'other_test_column',
          notNull: TRUE,
        ),
      ],
      primaryKey: new PrimaryKey($initial_primary_key),
    );
    $schema = new SchemaDefinition(
      type: SchemaDefinitionType::Test,
      name: $table_name,
      tables: [$table],
    );

    $this->schema->changeTableColumnFromDefinition(
      targetSchema: $schema,
      tableName: $table_name,
      targetColumnName: 'test_column',
    );
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_column'));
    $this->assertEquals($initial_primary_key, $find_primary_key_columns->invoke($this->schema, $table_name));

    // Add some data and change the column type back, to make sure that changing
    // the type leaves the primary key in place even with existing data.
    $this->connection
      ->insert($table_name)
      ->fields(['test_column' => 1, 'other_test_column' => 2])
      ->execute();

    $table = new Table(
      name: $table_name,
      description: 'Test table created from definition.',
      columns: [
        Column::int(
          name: 'test_column',
          notNull: TRUE,
        ),
        Column::int(
          name: 'other_test_column',
          notNull: TRUE,
        ),
      ],
      primaryKey: new PrimaryKey($initial_primary_key),
    );
    $schema = new SchemaDefinition(
      type: SchemaDefinitionType::Test,
      name: $table_name,
      tables: [$table],
    );

    $this->schema->changeTableColumnFromDefinition(
      targetSchema: $schema,
      tableName: $table_name,
      targetColumnName: 'test_column',
    );
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_column'));
    $this->assertEquals($initial_primary_key, $find_primary_key_columns->invoke($this->schema, $table_name));

    // Make sure that adding the primary key is done as part of changing a
    // column, as well, in case it is dropped explicitly before the change.
    $this->schema->dropPrimaryKey($table_name);
    $this->assertEquals([], $find_primary_key_columns->invoke($this->schema, $table_name));
    $this->schema->changeTableColumnFromDefinition(
      targetSchema: $schema,
      tableName: $table_name,
      targetColumnName: 'test_column',
    );
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_column'));
    $this->assertEquals($initial_primary_key, $find_primary_key_columns->invoke($this->schema, $table_name));

    // Rename the column and make sure the primary key was updated.
    $table = new Table(
      name: $table_name,
      description: 'Test table created from definition.',
      columns: [
        Column::int(
          name: 'test_column_renamed',
          notNull: TRUE,
        ),
        Column::int(
          name: 'other_test_column',
          notNull: TRUE,
        ),
      ],
      primaryKey: new PrimaryKey($renamed_primary_key),
    );
    $schema = new SchemaDefinition(
      type: SchemaDefinitionType::Test,
      name: $table_name,
      tables: [$table],
    );

    $this->schema->changeTableColumnFromDefinition(
      targetSchema: $schema,
      tableName: $table_name,
      currentColumnName: 'test_column',
      targetColumnName: 'test_column_renamed',
    );
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_column_renamed'));
    $this->assertEquals($renamed_primary_key, $find_primary_key_columns->invoke($this->schema, $table_name));

    // Drop the column and make sure the primary key was dropped, as well.
    $this->schema->dropField($table_name, 'test_column_renamed');
    $this->assertFalse($this->schema->fieldExists($table_name, 'test_column_renamed'));
    $this->assertEquals([], $find_primary_key_columns->invoke($this->schema, $table_name));

    // Add the column again and make sure adding the primary key can be done at
    // the same time.
    $table = new Table(
      name: $table_name,
      description: 'Test table created from definition.',
      columns: [
        Column::int(
          name: 'test_column',
          default: new IntValue(0),
          notNull: TRUE,
        ),
        Column::int(
          name: 'other_test_column',
          notNull: TRUE,
        ),
      ],
      primaryKey: new PrimaryKey($initial_primary_key),
    );
    $schema = new SchemaDefinition(
      type: SchemaDefinitionType::Test,
      name: $table_name,
      tables: [$table],
    );
    $this->schema->addTableColumnFromDefinition(
      targetSchema: $schema,
      tableName: $table_name,
      targetColumnName: 'test_column',
    );
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_column'));
    $this->assertEquals($initial_primary_key, $find_primary_key_columns->invoke($this->schema, $table_name));

    // Drop the column again and explicitly add a primary key.
    $this->assertEquals($initial_primary_key, $find_primary_key_columns->invoke($this->schema, $table_name));
    $this->schema->dropField($table_name, 'test_column');
    $this->assertFalse($this->schema->fieldExists($table_name, 'test_column'));
    $this->assertEquals([], $find_primary_key_columns->invoke($this->schema, $table_name));
    $this->schema->addPrimaryKey($table_name, ['other_test_column']);
    $this->assertEquals(['other_test_column'], $find_primary_key_columns->invoke($this->schema, $table_name));

    // Test that adding a column with a primary key will work even with a
    // pre-existing primary key.
    $this->schema->addTableColumnFromDefinition(
      targetSchema: $schema,
      tableName: $table_name,
      targetColumnName: 'test_column',
    );
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_column'));
    $this->assertEquals($initial_primary_key, $find_primary_key_columns->invoke($this->schema, $table_name));
  }

  /**
   * Provides test cases for SchemaTest::testSchemaCreateTablePrimaryKey().
   */
  public static function providerTestChangePrimaryKey(): \Generator {
    yield 'simple_primary_key' => [
      'initial_primary_key' => ['test_column'],
      'renamed_primary_key' => ['test_column_renamed'],
    ];
    yield 'composite_primary_key' => [
      'initial_primary_key' => ['test_column', 'other_test_column'],
      'renamed_primary_key' => ['test_column_renamed', 'other_test_column'],
    ];
    yield 'composite_primary_key_different_order' => [
      'initial_primary_key' => ['other_test_column', 'test_column'],
      'renamed_primary_key' => ['other_test_column', 'test_column_renamed'],
    ];
  }

}
