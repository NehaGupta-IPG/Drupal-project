<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\ColumnSize;
use Drupal\Core\Database\SchemaDefinition\ColumnType;
use Drupal\Core\Database\SchemaDefinition\FloatValue;
use Drupal\Core\Database\SchemaDefinition\Index;
use Drupal\Core\Database\SchemaDefinition\IntValue;
use Drupal\Core\Database\SchemaDefinition\NullValue;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\StringValue;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\Core\Database\SchemaDefinition\UniqueKey;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Database\Schema;
use Drupal\Core\Database\SchemaException;
use Drupal\Tests\Core\Database\SchemaIntrospectionTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests table creation and modification via the schema API.
 */
abstract class DriverSpecificSchemaTestBase extends DriverSpecificKernelTestBase {

  use SchemaIntrospectionTestTrait;

  /**
   * Database schema instance.
   */
  protected Schema $schema;

  /**
   * A global counter for table and field creation.
   */
  protected int $counter = 0;

  /**
   * Connection to the database.
   */
  protected Connection $connection;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->schema = $this->connection->schema();
  }

  /**
   * Checks that a table or column comment matches a given description.
   *
   * @param string|false $description
   *   The asserted description. Use FALSE to assert there is no description.
   * @param string $table
   *   The table to test.
   * @param string|null $column
   *   Optional column to test.
   */
  abstract public function checkSchemaComment(string|false $description, string $table, ?string $column = NULL): void;

  /**
   * Tests inserting data into an existing table.
   *
   * @param string $table
   *   The database table to insert data into.
   *
   * @return bool
   *   TRUE if the insert succeeded, FALSE otherwise.
   */
  public function tryInsert(string $table = 'test_table'): bool {
    try {
      $this->connection
        ->insert($table)
        ->fields(['id' => mt_rand(10, 20)])
        ->execute();
      return TRUE;
    }
    catch (\Exception) {
      return FALSE;
    }
  }

  /**
   * Tries to insert a negative value into columns defined as unsigned.
   *
   * @param string $table_name
   *   The table to insert.
   * @param string $column_name
   *   The column to insert.
   *
   * @return bool
   *   TRUE if the insert succeeded, FALSE otherwise.
   */
  public function tryUnsignedInsert(string $table_name, string $column_name): bool {
    try {
      $this->connection
        ->insert($table_name)
        ->fields([$column_name => -1])
        ->execute();
      return TRUE;
    }
    catch (\Exception) {
      return FALSE;
    }
  }

  /**
   * Tries to insert a value that throws an IntegrityConstraintViolationException.
   *
   * @param string $tableName
   *   The table to insert.
   */
  protected function tryInsertExpectsIntegrityConstraintViolationException(string $tableName): void {
    try {
      $this->connection
        ->insert($tableName)
        ->fields(['test_field_string' => 'test'])
        ->execute();
      $this->fail('Expected IntegrityConstraintViolationException not thrown');
    }
    catch (IntegrityConstraintViolationException) {
      // Do nothing, it's the expected behavior.
    }
  }

  /**
   * Asserts that fields have the correct collation, if supported.
   */
  protected function assertCollation(): void {
    // Driver specific tests should implement this when appropriate.
  }

  /**
   * Check that the ID sequence gets renamed when the table is renamed.
   *
   * @param string $tableName
   *   The table to rename.
   */
  protected function checkSequenceRenaming(string $tableName): void {
    // Driver specific tests should implement this when appropriate.
  }

  /**
   * Tests database interactions.
   */
  public function testSchema(): void {
    // Try creating a table.
    $table_definition = new Table(
      name: 'test_table',
      description: 'Schema table description may contain "quotes" and could be long—very long indeed.',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Int,
          default: new NullValue(),
        ),
        new Column(
          name: 'test_field',
          description: 'Schema table description may contain "quotes" and could be long—very long indeed. There could be "multiple quoted regions".',
          type: ColumnType::Int,
          notNull: TRUE,
        ),
        new Column(
          name: 'test_field_string',
          description: 'Schema column description for string.',
          type: ColumnType::Varchar,
          length: 20,
          notNull: TRUE,
          default: new StringValue("'\"funky default'\""),
        ),
        new Column(
          name: 'test_field_string_ascii',
          description: 'Schema column description for ASCII string.',
          type: ColumnType::VarcharAscii,
          length: 255,
        ),
      ],
    );
    $this->schema->createTableFromDefinition($table_definition);

    // Assert that the table exists.
    $this->assertTrue($this->schema->tableExists('test_table'));

    // Assert that the table comment has been set.
    $this->checkSchemaComment($table_definition->description, 'test_table');

    // Assert that the column comment has been set.
    $this->checkSchemaComment($table_definition->getColumnDefinition('test_field')->description, 'test_table', 'test_field');

    // Make sure that fields have the correct collation, if supported.
    $this->assertCollation();

    // An insert without a value for the column 'test_table' should fail.
    $this->assertFalse($this->tryInsert(), 'Insert without a default failed.');

    // Add a default value to the column.
    $this->schema->changeField(
      'test_table',
      'test_field',
      'test_field',
      ['type' => 'int', 'not null' => TRUE, 'default' => 0]
    );
    // The insert should now succeed.
    $this->assertTrue($this->tryInsert(), 'Insert with a default succeeded.');

    // Remove the default.
    $this->schema->changeField('test_table', 'test_field', 'test_field', ['type' => 'int', 'not null' => TRUE]);
    // The insert should fail again.
    $this->assertFalse($this->tryInsert(), 'Insert without a default failed.');

    // Test for fake index and test for the boolean result of indexExists().
    $index_exists = $this->schema->indexExists('test_table', 'test_field');
    $this->assertFalse($index_exists, 'Fake index does not exist');
    // Add index.
    $this->schema->addIndex('test_table', 'test_field', ['test_field'], $table_definition->toArray());
    // Test for created index and test for the boolean result of indexExists().
    $index_exists = $this->schema->indexExists('test_table', 'test_field');
    $this->assertTrue($index_exists, 'Index created.');

    // Rename the table.
    $this->assertNull($this->schema->renameTable('test_table', 'test_table2'));

    // Index should be renamed.
    $index_exists = $this->schema->indexExists('test_table2', 'test_field');
    $this->assertTrue($index_exists, 'Index was renamed.');

    // We need the default so that we can insert after the rename.
    $this->schema->changeField(
      'test_table2',
      'test_field',
      'test_field',
      ['type' => 'int', 'not null' => TRUE, 'default' => 0]
    );
    $this->assertFalse($this->tryInsert(), 'Insert into the old table failed.');
    $this->assertTrue($this->tryInsert('test_table2'), 'Insert into the new table succeeded.');

    // We should have successfully inserted exactly two rows.
    $count = $this->connection->query('SELECT COUNT(*) FROM {test_table2}')->fetchField();
    $this->assertEquals(2, $count, 'Two fields were successfully inserted.');

    // Try to drop the table.
    $this->schema->dropTable('test_table2');
    $this->assertFalse($this->schema->tableExists('test_table2'), 'The dropped table does not exist.');

    // Recreate the table.
    $this->schema->createTableFromDefinition($table_definition);
    $this->schema->changeField(
      'test_table',
      'test_field',
      'test_field',
      ['type' => 'int', 'not null' => TRUE, 'default' => 0]
    );
    $this->schema->addField(
      'test_table',
      'test_serial',
      ['type' => 'int', 'not null' => TRUE, 'default' => 0, 'description' => 'Added column description.']
    );

    // Assert that the column comment has been set.
    $this->checkSchemaComment('Added column description.', 'test_table', 'test_serial');

    // Change the new field to a serial column.
    $this->schema->changeField(
      'test_table',
      'test_serial',
      'test_serial',
      ['type' => 'serial', 'not null' => TRUE, 'description' => 'Changed column description.'],
      ['primary key' => ['test_serial']]
    );

    // Assert that the column comment has been set.
    $this->checkSchemaComment('Changed column description.', 'test_table', 'test_serial');

    $this->assertTrue($this->tryInsert(), 'Insert with a serial succeeded.');
    $max1 = $this->connection->query('SELECT MAX([test_serial]) FROM {test_table}')->fetchField();
    $this->assertTrue($this->tryInsert(), 'Insert with a serial succeeded.');
    $max2 = $this->connection->query('SELECT MAX([test_serial]) FROM {test_table}')->fetchField();
    $this->assertTrue($max2 > $max1, 'The serial is monotone.');

    $count = $this->connection->query('SELECT COUNT(*) FROM {test_table}')->fetchField();
    $this->assertEquals(2, $count, 'There were two rows.');

    // Test adding a serial field to an existing table.
    $this->schema->dropTable('test_table');
    $this->schema->createTableFromDefinition($table_definition);
    $this->schema->changeField(
      'test_table',
      'test_field',
      'test_field',
      ['type' => 'int', 'not null' => TRUE, 'default' => 0]
    );
    $this->schema->addField('test_table', 'test_serial', ['type' => 'serial', 'not null' => TRUE], ['primary key' => ['test_serial']]);

    // Test the primary key columns.
    $method = new \ReflectionMethod(get_class($this->schema), 'findPrimaryKeyColumns');
    $this->assertSame(['test_serial'], $method->invoke($this->schema, 'test_table'));

    $this->assertTrue($this->tryInsert(), 'Insert with a serial succeeded.');
    $max1 = $this->connection->query('SELECT MAX([test_serial]) FROM {test_table}')->fetchField();
    $this->assertTrue($this->tryInsert(), 'Insert with a serial succeeded.');
    $max2 = $this->connection->query('SELECT MAX([test_serial]) FROM {test_table}')->fetchField();
    $this->assertTrue($max2 > $max1, 'The serial is monotone.');

    $count = $this->connection->query('SELECT COUNT(*) FROM {test_table}')->fetchField();
    $this->assertEquals(2, $count, 'There were two rows.');

    // Test adding a new column and form a composite primary key with it.
    $this->schema->addField(
      'test_table',
      'test_composite_primary_key',
      ['type' => 'int', 'not null' => TRUE, 'default' => 0],
      ['primary key' => ['test_serial', 'test_composite_primary_key']]
    );

    // Test the primary key columns.
    $this->assertSame(['test_serial', 'test_composite_primary_key'], $method->invoke($this->schema, 'test_table'));

    // Test adding and removing JSON column.
    $this->schema->addField('test_table', 'test_json', [
      'description' => 'I heard you liked JSON.',
      'type' => 'json',
      'pgsql_type' => 'jsonb',
      'mysql_type' => 'json',
      'sqlite_type' => 'json',
    ]);
    $this->schema->dropField('test_table', 'test_json');

    // Test renaming of keys and constraints.
    $this->schema->dropTable('test_table');

    // PostgreSQL has a max identifier length of 63 characters, MySQL has 64 and
    // SQLite does not have any limit. Use the lowest common value and create a
    // table name as long as possible in order to cover edge cases around
    // identifier names for the table's primary or unique key constraints.
    $table_name = strtolower($this->getRandomGenerator()->name(63 - strlen($this->getDatabasePrefix())));
    $this->schema->createTableFromDefinition(
      new Table(
        name: $table_name,
        columns: [
          new Column(
            name: 'id',
            type: ColumnType::Serial,
          ),
          new Column(
            name: 'test_field',
            type: ColumnType::Int,
            default: new IntValue(0),
          ),
        ],
        primaryKey: new PrimaryKey(['id']),
        uniqueKeys: [
          new UniqueKey(name: 'test_field', columns: ['test_field']),
        ],
      ),
    );

    $this->assertIndexOnColumns($table_name, ['id'], 'primary');
    $this->assertIndexOnColumns($table_name, ['test_field'], 'unique');

    $new_table_name = strtolower($this->getRandomGenerator()->name(63 - strlen($this->getDatabasePrefix())));
    $this->assertNull($this->schema->renameTable($table_name, $new_table_name));

    // Test for renamed primary and unique keys.
    $this->assertIndexOnColumns($new_table_name, ['id'], 'primary');
    $this->assertIndexOnColumns($new_table_name, ['test_field'], 'unique');

    // Check that the ID sequence gets renamed when the table is renamed.
    $this->checkSequenceRenaming($new_table_name);
  }

  /**
   * Tests creating a table with database specific data type.
   */
  abstract public function testTableWithSpecificDataType(): void;

  /**
   * Tests creating unsigned columns and data integrity thereof.
   */
  public function testUnsignedColumns(): void {
    // First create the table with just a serial column.
    $table_name = 'unsigned_table';
    $table_definition = new Table(
      name: $table_name,
      columns: [
        new Column(
          name: 'serial_column',
          type: ColumnType::Serial,
        ),
      ],
      primaryKey: new PrimaryKey(['serial_column']),
    );
    $this->schema->createTableFromDefinition($table_definition);

    // Now set up columns for the other types.
    $types = ['int', 'float', 'numeric'];
    foreach ($types as $type) {
      $column_spec = ['type' => $type, 'unsigned' => TRUE];
      if ($type == 'numeric') {
        $column_spec += ['precision' => 10, 'scale' => 0];
      }
      $column_name = $type . '_column';
      $table_spec['fields'][$column_name] = $column_spec;
      $this->schema->addField($table_name, $column_name, $column_spec);
    }

    // Finally, check each column and try to insert invalid values into them.
    foreach ($table_definition->columns as $column) {
      $this->assertTrue($this->schema->fieldExists($table_name, $column->name), "Unsigned {$column->type->value} column was created.");
      $this->assertFalse($this->tryUnsignedInsert($table_name, $column->name), "Unsigned {$column->type->value} column rejected a negative value.");
    }
  }

  /**
   * Tests adding columns to an existing table with default and initial value.
   */
  public function testSchemaAddFieldDefaultInitial(): void {
    // Test varchar types.
    foreach ([1, 32, 128, 256, 512] as $length) {
      $base_field_spec = [
        'name' => 'test_field',
        'type' => ColumnType::Varchar,
        'length' => $length,
      ];
      $variations = [
        ['notNull' => FALSE],
        ['notNull' => FALSE, 'default' => new StringValue('7')],
        ['notNull' => FALSE, 'default' => new StringValue(substr('"thing"', 0, $length))],
        ['notNull' => FALSE, 'default' => new StringValue(substr("\"'hing", 0, $length))],
        ['notNull' => TRUE, 'initial' => 'd'],
        ['notNull' => FALSE, 'default' => new NullValue()],
        ['notNull' => TRUE, 'initial' => 'd', 'default' => new StringValue('7')],
      ];

      foreach ($variations as $variation) {
        $field_spec = $variation + $base_field_spec;
        $initial = $field_spec['initial'] ?? NULL;
        unset($field_spec['initial']);
        $this->assertFieldAdditionRemoval(new Column(...$field_spec), $initial);
      }
    }

    // Test int and float types.
    foreach ([ColumnType::Int, ColumnType::Float] as $type) {
      foreach ([ColumnSize::Tiny, ColumnSize::Small, ColumnSize::Medium, ColumnSize::Normal, ColumnSize::Big] as $size) {
        $base_field_spec = [
          'name' => 'test_field',
          'type' => $type,
          'size' => $size,
        ];
        $variations = [
          [
            'notNull' => FALSE,
          ],
          [
            'notNull' => FALSE,
            'default' => $type === ColumnType::Int ? new IntValue(7) : new FloatValue(7),
          ],
          [
            'notNull' => TRUE,
            'initial' => 1,
          ],
          [
            'notNull' => TRUE,
            'initial' => 1,
            'default' => $type === ColumnType::Int ? new IntValue(7) : new FloatValue(7),
          ],
          [
            'notNull' => TRUE,
            'initial_from_field' => 'serial_column',
          ],
          [
            'notNull' => TRUE,
            'initial_from_field' => 'test_nullable_field',
            'initial'  => 100,
          ],
        ];

        foreach ($variations as $variation) {
          $field_spec = $variation + $base_field_spec;
          $initial = $field_spec['initial'] ?? NULL;
          $initial_from_field = $field_spec['initial_from_field'] ?? NULL;
          unset($field_spec['initial'], $field_spec['initial_from_field']);
          $this->assertFieldAdditionRemoval(new Column(...$field_spec), $initial, $initial_from_field);
        }
      }
    }

    // Test numeric types.
    foreach ([1, 5, 10, 40, 65] as $precision) {
      foreach ([0, 2, 10, 30] as $scale) {
        // Skip combinations where precision is smaller than scale.
        if ($precision <= $scale) {
          continue;
        }

        $base_field_spec = [
          'name' => 'test_field',
          'type' => ColumnType::Numeric,
          'scale' => $scale,
          'precision' => $precision,
        ];
        $variations = [
          ['notNull' => FALSE],
          ['notNull' => FALSE, 'default' => new FloatValue(7)],
          ['notNull' => TRUE, 'initial' => 1],
          ['notNull' => TRUE, 'initial' => 1, 'default' => new FloatValue(7)],
          ['notNull' => TRUE, 'initial_from_field' => 'serial_column'],
        ];

        foreach ($variations as $variation) {
          $field_spec = $variation + $base_field_spec;
          $initial = $field_spec['initial'] ?? NULL;
          $initial_from_field = $field_spec['initial_from_field'] ?? NULL;
          unset($field_spec['initial'], $field_spec['initial_from_field']);
          $this->assertFieldAdditionRemoval(new Column(...$field_spec), $initial, $initial_from_field);
        }
      }
    }
  }

  /**
   * Asserts that a given field can be added and removed from a table.
   *
   * The addition test covers both defining a field of a given specification
   * when initially creating at table and extending an existing table.
   *
   * @param array $field_spec
   *   The schema specification of the field.
   * @param int|float|string|null $initial
   *   (Optional) The value to use for new field initialization.
   * @param string|null $initial_from_field
   *   (Optional) The field whose values should be used use for new field
   *   initialization.
   *
   * @internal
   */
  protected function assertFieldAdditionRemoval(
    Column $field_spec,
    int|float|string|null $initial = NULL,
    string|null $initial_from_field = NULL,
  ): void {
    $field_spec_for_schema_method = $field_spec->toArray();
    if (isset($initial)) {
      $field_spec_for_schema_method['initial'] = $initial;
    }
    if (isset($initial_from_field)) {
      $field_spec_for_schema_method['initial_from_field'] = $initial_from_field;
    }

    // Try creating the field on a new table.
    $table_name = 'test_table_' . ($this->counter++);
    $table_definition = new Table(
      name: $table_name,
      columns: [
        new Column(
          name: 'serial_column',
          type: ColumnType::Serial,
        ),
        new Column(
          name: 'test_nullable_field',
          type: ColumnType::Int,
          notNull: FALSE,
        ),
        $field_spec,
      ],
      primaryKey: new PrimaryKey(['serial_column']),
    );
    $this->schema->createTableFromDefinition($table_definition);

    // Check the characteristics of the field.
    $this->assertFieldCharacteristics($table_name, 'test_field', $field_spec, $initial, $initial_from_field);

    // Clean-up.
    $this->schema->dropTable($table_name);

    // Try adding a field to an existing table.
    $table_name = 'test_table_' . ($this->counter++);
    $table_definition = new Table(
      name: $table_name,
      columns: [
        new Column(
          name: 'serial_column',
          type: ColumnType::Serial,
        ),
        new Column(
          name: 'test_nullable_field',
          type: ColumnType::Int,
          notNull: FALSE,
        ),
      ],
      primaryKey: new PrimaryKey(['serial_column']),
    );
    $this->schema->createTableFromDefinition($table_definition);

    // Insert some rows to the table to test the handling of initial values.
    for ($i = 0; $i < 3; $i++) {
      $this->connection
        ->insert($table_name)
        ->useDefaults(['serial_column'])
        ->fields(['test_nullable_field' => 100])
        ->execute();
    }

    // Add another row with no value for the 'test_nullable_field' column.
    $this->connection
      ->insert($table_name)
      ->useDefaults(['serial_column'])
      ->execute();

    $this->schema->addField($table_name, 'test_field', $field_spec_for_schema_method);

    // Check the characteristics of the field.
    $this->assertFieldCharacteristics($table_name, 'test_field', $field_spec, $initial, $initial_from_field);

    // Clean-up.
    $this->schema->dropField($table_name, 'test_field');

    // Add back the field and then try to delete a field which is also a primary
    // key.
    $this->schema->addField($table_name, 'test_field', $field_spec_for_schema_method);
    $this->schema->dropField($table_name, 'serial_column');
    $this->schema->dropTable($table_name);
  }

  /**
   * Asserts that a newly added field has the correct characteristics.
   *
   * @internal
   */
  protected function assertFieldCharacteristics(
    string $table_name,
    string $field_name,
    Column $field_spec,
    int|float|string|null $initial = NULL,
    string|null $initial_from_field = NULL,
  ): void {
    // Check that the initial value has been registered.
    if (isset($initial)) {
      // There should be no row with a value different then
      // $initial'].
      $count = $this->connection
        ->select($table_name)
        ->fields($table_name, ['serial_column'])
        ->condition($field_name, $initial, '<>')
        ->countQuery()
        ->execute()
        ->fetchField();
      $this->assertEquals(0, $count, 'Initial values filled out.');
    }

    // Check that the initial value from another field has been registered.
    if (isset($initial_from_field) && !isset($initial)) {
      // There should be no row with a value different than
      // $initial_from_field'].
      $count = $this->connection
        ->select($table_name)
        ->fields($table_name, ['serial_column'])
        ->where("[$table_name].[{$initial_from_field}] <> [$table_name].[$field_name]")
        ->countQuery()
        ->execute()
        ->fetchField();
      $this->assertEquals(0, $count, 'Initial values from another field filled out.');
    }
    elseif (isset($initial_from_field) && isset($initial)) {
      // There should be no row with a value different than '100'.
      $count = $this->connection
        ->select($table_name)
        ->fields($table_name, ['serial_column'])
        ->condition($field_name, 100, '<>')
        ->countQuery()
        ->execute()
        ->fetchField();
      $this->assertEquals(0, $count, 'Initial values from another field or a default value filled out.');
    }

    // Check that the default value has been registered.
    if ($field_spec->default) {
      // Try inserting a row, and check the resulting value of the new column.
      $id = $this->connection
        ->insert($table_name)
        ->useDefaults(['serial_column'])
        ->execute();
      $field_value = $this->connection
        ->select($table_name)
        ->fields($table_name, [$field_name])
        ->condition('serial_column', $id)
        ->execute()
        ->fetchField();
      $this->assertEquals($field_spec->default->value, $field_value, 'Default value registered.');
    }
  }

  /**
   * Tests various schema changes' effect on the table's primary key.
   *
   * @param \Drupal\Core\Database\SchemaDefinition\PrimaryKey $initial_primary_key
   *   The initial primary key of the test table.
   * @param \Drupal\Core\Database\SchemaDefinition\PrimaryKey $renamed_primary_key
   *   The primary key of the test table after renaming the test field.
   *
   * @legacy-covers ::addField
   * @legacy-covers ::changeField
   * @legacy-covers ::dropField
   * @legacy-covers ::findPrimaryKeyColumns
   */
  #[DataProvider('providerTestSchemaCreateTablePrimaryKey')]
  public function testSchemaChangePrimaryKey(PrimaryKey $initial_primary_key, PrimaryKey $renamed_primary_key): void {
    $find_primary_key_columns = new \ReflectionMethod(get_class($this->schema), 'findPrimaryKeyColumns');

    // Test making the field the primary key of the table upon creation.
    $table_name = 'test_table';
    $this->schema->createTableFromDefinition(new Table(
      name: $table_name,
      columns: [
        new Column(
          name: 'test_field',
          type: ColumnType::Int,
          notNull: TRUE,
        ),
        new Column(
          name: 'other_test_field',
          type: ColumnType::Int,
          notNull: TRUE,
        ),
      ],
      primaryKey: $initial_primary_key,
    ));
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_field'));
    $this->assertEquals($initial_primary_key, new PrimaryKey($find_primary_key_columns->invoke($this->schema, $table_name)));

    // Change the field type and make sure the primary key stays in place.
    $this->schema->changeField(
      $table_name,
      'test_field',
      'test_field',
      ['type' => 'varchar', 'length' => 32, 'not null' => TRUE]
    );
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_field'));
    $this->assertEquals($initial_primary_key, new PrimaryKey($find_primary_key_columns->invoke($this->schema, $table_name)));

    // Add some data and change the field type back, to make sure that changing
    // the type leaves the primary key in place even with existing data.
    $this->connection
      ->insert($table_name)
      ->fields(['test_field' => 1, 'other_test_field' => 2])
      ->execute();
    $this->schema->changeField($table_name, 'test_field', 'test_field', ['type' => 'int', 'not null' => TRUE]);
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_field'));
    $this->assertEquals($initial_primary_key, new PrimaryKey($find_primary_key_columns->invoke($this->schema, $table_name)));

    // Make sure that adding the primary key can be done as part of changing
    // a field, as well.
    $this->schema->dropPrimaryKey($table_name);
    $this->assertEquals([], $find_primary_key_columns->invoke($this->schema, $table_name));
    $this->schema->changeField($table_name, 'test_field', 'test_field', ['type' => 'int', 'not null' => TRUE], ['primary key' => $initial_primary_key->toArray()]);
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_field'));
    $this->assertEquals($initial_primary_key, new PrimaryKey($find_primary_key_columns->invoke($this->schema, $table_name)));

    // Rename the field and make sure the primary key was updated.
    $this->schema->changeField($table_name, 'test_field', 'test_field_renamed', ['type' => 'int', 'not null' => TRUE]);
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_field_renamed'));
    $this->assertEquals($renamed_primary_key, new PrimaryKey($find_primary_key_columns->invoke($this->schema, $table_name)));

    // Drop the field and make sure the primary key was dropped, as well.
    $this->schema->dropField($table_name, 'test_field_renamed');
    $this->assertFalse($this->schema->fieldExists($table_name, 'test_field_renamed'));
    $this->assertEquals([], $find_primary_key_columns->invoke($this->schema, $table_name));

    // Add the field again and make sure adding the primary key can be done at
    // the same time.
    $this->schema->addField($table_name, 'test_field', ['type' => 'int', 'default' => 0, 'not null' => TRUE], ['primary key' => $initial_primary_key->toArray()]);
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_field'));
    $this->assertEquals($initial_primary_key, new PrimaryKey($find_primary_key_columns->invoke($this->schema, $table_name)));

    // Drop the field again and explicitly add a primary key.
    $this->schema->dropField($table_name, 'test_field');
    $this->schema->addPrimaryKey($table_name, ['other_test_field']);
    $this->assertFalse($this->schema->fieldExists($table_name, 'test_field'));
    $this->assertEquals(['other_test_field'], $find_primary_key_columns->invoke($this->schema, $table_name));

    // Test that adding a field with a primary key will work even with a
    // pre-existing primary key.
    $this->schema->addField($table_name, 'test_field', ['type' => 'int', 'default' => 0, 'not null' => TRUE], ['primary key' => $initial_primary_key->toArray()]);
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_field'));
    $this->assertEquals($initial_primary_key, new PrimaryKey($find_primary_key_columns->invoke($this->schema, $table_name)));
  }

  /**
   * Provides test cases for SchemaTest::testSchemaCreateTablePrimaryKey().
   *
   * @return array
   *   An array of test cases for SchemaTest::testSchemaCreateTablePrimaryKey().
   */
  public static function providerTestSchemaCreateTablePrimaryKey() {
    $tests = [];

    $tests['simple_primary_key'] = [
      'initial_primary_key' => new PrimaryKey(['test_field']),
      'renamed_primary_key' => new PrimaryKey(['test_field_renamed']),
    ];
    $tests['composite_primary_key'] = [
      'initial_primary_key' => new PrimaryKey(['test_field', 'other_test_field']),
      'renamed_primary_key' => new PrimaryKey(['test_field_renamed', 'other_test_field']),
    ];
    $tests['composite_primary_key_different_order'] = [
      'initial_primary_key' => new PrimaryKey(['other_test_field', 'test_field']),
      'renamed_primary_key' => new PrimaryKey(['other_test_field', 'test_field_renamed']),
    ];

    return $tests;
  }

  /**
   * Tests an invalid field specification as a primary key on table creation.
   */
  public function testInvalidPrimaryKeyOnTableCreation(): void {
    // Test making an invalid field the primary key of the table upon creation.
    $this->expectException(SchemaException::class);
    $this->expectExceptionMessage("The 'test_field' field specification does not define 'not null' as TRUE.");
    $this->schema->createTableFromDefinition(new Table(
      name: 'test_table',
      columns: [
        new Column(
          name: 'test_field',
          type: ColumnType::Int,
        ),
      ],
      primaryKey: new PrimaryKey(['test_field']),
    ));
  }

  /**
   * Tests converting an int to a serial when the int column has data.
   */
  public function testChangePrimaryKeyToSerial(): void {
    // Test making an invalid field the primary key of the table upon creation.
    $table_name = 'test_table';
    $this->schema->createTableFromDefinition(new Table(
      name: $table_name,
      columns: [
        new Column(
          name: 'test_field',
          type: ColumnType::Int,
          notNull: TRUE,
        ),
        new Column(
          name: 'test_field_string',
          type: ColumnType::Varchar,
          length: 20,
        ),
      ],
      primaryKey: new PrimaryKey(['test_field']),
    ));

    $this->tryInsertExpectsIntegrityConstraintViolationException($table_name);

    // @todo https://www.drupal.org/project/drupal/issues/3222127 Change the
    //   first item to 0 to test changing a field with 0 to a serial.
    // Create 8 rows in the table. Note that the 5 value is deliberately
    // omitted.
    foreach ([1, 2, 3, 4, 6, 7, 8, 9] as $value) {
      $this->connection
        ->insert($table_name)
        ->fields(['test_field' => $value])
        ->execute();
    }
    $this->schema->changeField($table_name, 'test_field', 'test_field', ['type' => 'serial', 'not null' => TRUE]);

    $data = $this->connection
      ->select($table_name)
      ->fields($table_name, ['test_field'])
      ->execute()
      ->fetchCol();
    $this->assertEquals([1, 2, 3, 4, 6, 7, 8, 9], array_values($data));

    try {
      $this->connection
        ->insert($table_name)
        ->fields(['test_field' => 1])
        ->execute();
      $this->fail('Expected IntegrityConstraintViolationException not thrown');
    }
    catch (IntegrityConstraintViolationException) {
    }

    // Ensure auto numbering now works.
    // We use a >= assertion to allow non-core drivers, that may have specific
    // strategies on automatic incrementing, to run core tests. For example,
    // Oracle will allocate a 10 id with the previous insert that was meant to
    // fail; that id will be discarded, and the insert here will get a new 11
    // id instead.
    $id = $this->connection
      ->insert($table_name)
      ->fields(['test_field_string' => 'test'])
      ->execute();
    $this->assertGreaterThanOrEqual(10, $id);
  }

  /**
   * Tests adding an invalid field specification as a primary key.
   */
  public function testInvalidPrimaryKeyAddition(): void {
    // Test adding a new invalid field to the primary key.
    $table_name = 'test_table';
    $this->schema->createTableFromDefinition(new Table(
      name: $table_name,
      columns: [
        new Column(
          name: 'test_field',
          type: ColumnType::Int,
          notNull: TRUE,
        ),
      ],
      primaryKey: new PrimaryKey(['test_field']),
    ));

    $this->expectException(SchemaException::class);
    $this->expectExceptionMessage("The 'new_test_field' field specification does not define 'not null' as TRUE.");
    $this->schema->addField(
      $table_name,
      'new_test_field',
      ['type' => 'int'],
      ['primary key' => ['test_field', 'new_test_field']]
    );
  }

  /**
   * Tests changing the primary key with an invalid field specification.
   */
  public function testInvalidPrimaryKeyChange(): void {
    // Test adding a new invalid field to the primary key.
    $table_name = 'test_table';
    $this->schema->createTableFromDefinition(new Table(
      name: $table_name,
      columns: [
        new Column(
          name: 'test_field',
          type: ColumnType::Int,
          notNull: TRUE,
        ),
      ],
      primaryKey: new PrimaryKey(['test_field']),
    ));

    $this->expectException(SchemaException::class);
    $this->expectExceptionMessage("The 'changed_test_field' field specification does not define 'not null' as TRUE.");
    $this->schema->dropPrimaryKey($table_name);
    $this->schema->changeField($table_name, 'test_field', 'changed_test_field', ['type' => 'int'], ['primary key' => ['changed_test_field']]);
  }

  /**
   * Tests changing columns between types with default and initial values.
   */
  public function testSchemaChangeFieldDefaultInitial(): void {
    $field_specs = [
      [
        'column' => new Column(
          name: 'test_field',
          type: ColumnType::Int,
          size: ColumnSize::Normal,
          notNull: FALSE,
        ),
      ],
      [
        'column' => new Column(
          name: 'test_field',
          type: ColumnType::Int,
          size: ColumnSize::Normal,
          notNull: TRUE,
          default: new IntValue(17),
        ),
        'initial' => 1,
      ],
      [
        'column' => new Column(
          name: 'test_field',
          type: ColumnType::Float,
          size: ColumnSize::Normal,
          notNull: FALSE,
        ),
      ],
      [
        'column' => new Column(
          name: 'test_field',
          type: ColumnType::Float,
          size: ColumnSize::Normal,
          notNull: TRUE,
          default: new FloatValue(7.3),
        ),
        'initial' => 1,
      ],
      [
        'column' => new Column(
          name: 'test_field',
          type: ColumnType::Numeric,
          scale: 2,
          precision: 10,
          notNull: FALSE,
        ),
      ],
      [
        'column' => new Column(
          name: 'test_field',
          type: ColumnType::Numeric,
          scale: 2,
          precision: 10,
          notNull: TRUE,
          default: new FloatValue(7),
        ),
        'initial' => 1,
      ],
    ];

    foreach ($field_specs as $i => $old_spec) {
      foreach ($field_specs as $j => $new_spec) {
        if ($i === $j) {
          // Do not change a field into itself.
          continue;
        }
        $this->assertFieldChange($old_spec, $new_spec);
      }
    }

    $field_specs = [
      [
        'column' => new Column(
          name: 'test_field',
          type: ColumnType::VarcharAscii,
          length: 255,
        ),
      ],
      [
        'column' => new Column(
          name: 'test_field',
          type: ColumnType::Varchar,
          length: 255,
        ),
      ],
      [
        'column' => new Column(
          name: 'test_field',
          type: ColumnType::Text,
        ),
      ],
      [
        'column' => new Column(
          name: 'test_field',
          type: ColumnType::Blob,
          size: ColumnSize::Big,
        ),
      ],
    ];

    foreach ($field_specs as $i => $old_spec) {
      foreach ($field_specs as $j => $new_spec) {
        if ($i === $j) {
          // Do not change a field into itself.
          continue;
        }
        // Note if the serialized data contained an object this would fail on
        // Postgres.
        // @see https://www.drupal.org/node/1031122
        $this->assertFieldChange($old_spec, $new_spec, serialize(['string' => "This \n has \\\\ some backslash \"*string action.\\n"]));
      }
    }

  }

  /**
   * Asserts that a field can be changed from one spec to another.
   *
   * @param array $old_spec
   *   The beginning field specification.
   * @param array $new_spec
   *   The ending field specification.
   * @param mixed $test_data
   *   (optional) A test value to insert and test, if specified.
   *
   * @internal
   */
  protected function assertFieldChange(array $old_spec, array $new_spec, $test_data = NULL): void {
    $table_name = 'test_table_' . ($this->counter++);
    $table_definition = new Table(
      name: $table_name,
      columns: [
        new Column(
          name: 'serial_column',
          type: ColumnType::Serial,
        ),
        $old_spec['column'],
      ],
      primaryKey: new PrimaryKey(['serial_column']),
    );
    $this->schema->createTableFromDefinition($table_definition);

    // Check the characteristics of the field.
    $this->assertFieldCharacteristics($table_name, 'test_field', $old_spec['column'], $old_spec['initial'] ?? NULL, $old_spec['initial_from_field'] ?? NULL);

    // Remove inserted rows.
    $this->connection->truncate($table_name)->execute();

    if ($test_data) {
      $id = $this->connection
        ->insert($table_name)
        ->fields(['test_field'], [$test_data])
        ->execute();
    }

    // Change the field.
    $new_spec_for_schema_method = $new_spec['column']->toArray();
    if (isset($new_spec['initial'])) {
      $new_spec_for_schema_method['initial'] = $new_spec['initial'];
    }
    if (isset($new_spec['initial_from_field'])) {
      $new_spec_for_schema_method['initial_from_field'] = $new_spec['initial_from_field'];
    }
    $this->schema->changeField($table_name, 'test_field', 'test_field', $new_spec_for_schema_method);

    if ($test_data) {
      $field_value = $this->connection
        ->select($table_name)
        ->fields($table_name, ['test_field'])
        ->condition('serial_column', $id)
        ->execute()
        ->fetchField();
      $this->assertSame($test_data, $field_value);
    }

    // Check the field was changed.
    $this->assertFieldCharacteristics($table_name, 'test_field', $new_spec['column'], $new_spec['initial'] ?? NULL, $new_spec['initial_from_field'] ?? NULL);

    // Clean-up.
    $this->schema->dropTable($table_name);
  }

  /**
   * Tests find primary key columns.
   */
  public function testFindPrimaryKeyColumns(): void {
    $method = new \ReflectionMethod(get_class($this->schema), 'findPrimaryKeyColumns');

    // Test with single column primary key.
    $this->schema->createTableFromDefinition(
      new Table(
        name: 'table_with_pk_0',
        description: 'Table with primary key.',
        columns: [
          new Column(
            name: 'id',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
          new Column(
            name: 'test_field',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
        ],
        primaryKey: new PrimaryKey(['id']),
      ),
    );
    $this->assertSame(['id'], $method->invoke($this->schema, 'table_with_pk_0'));

    // Test with multiple column primary key.
    $this->schema->createTableFromDefinition(
      new Table(
        name: 'table_with_pk_1',
        description: 'Table with primary key with multiple columns.',
        columns: [
          new Column(
            name: 'id0',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
          new Column(
            name: 'id1',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
          new Column(
            name: 'test_field',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
        ],
        primaryKey: new PrimaryKey(['id0', 'id1']),
      ),
    );
    $this->assertSame(['id0', 'id1'], $method->invoke($this->schema, 'table_with_pk_1'));

    // Test with multiple column primary key and not being the first column of
    // the table definition.
    $this->schema->createTableFromDefinition(
      new Table(
        name: 'table_with_pk_2',
        description: 'Table with primary key with multiple columns at the end and in reverted sequence.',
        columns: [
          new Column(
            name: 'test_field_1',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
          new Column(
            name: 'test_field_2',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
          new Column(
            name: 'id3',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
          new Column(
            name: 'id4',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
        ],
        primaryKey: new PrimaryKey(['id4', 'id3']),
      ),
    );
    $this->assertSame(['id4', 'id3'], $method->invoke($this->schema, 'table_with_pk_2'));

    // Test with multiple column primary key in a different order. For the
    // PostgreSQL and the SQLite drivers is sorting used to get the primary key
    // columns in the right order.
    $this->schema->createTableFromDefinition(
      new Table(
        name: 'table_with_pk_3',
        description: 'Table with primary key with multiple columns at the end and in reverted sequence.',
        columns: [
          new Column(
            name: 'test_field_1',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
          new Column(
            name: 'test_field_2',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
          new Column(
            name: 'id3',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
          new Column(
            name: 'id4',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
        ],
        primaryKey: new PrimaryKey(['id3', 'test_field_2', 'id4']),
      ),
    );
    $this->assertSame(['id3', 'test_field_2', 'id4'], $method->invoke($this->schema, 'table_with_pk_3'));

    // Test with table without a primary key.
    $this->schema->createTableFromDefinition(
      new Table(
        name: 'table_without_pk_1',
        description: 'Table without primary key.',
        columns: [
          new Column(
            name: 'id',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
          new Column(
            name: 'test_field',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
        ],
      ),
    );
    $this->assertSame([], $method->invoke($this->schema, 'table_without_pk_1'));

    // Test with table with an empty primary key.
    $this->schema->createTableFromDefinition(
      new Table(
        name: 'table_without_pk_2',
        description: 'Table without primary key.',
        columns: [
          new Column(
            name: 'id',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
          new Column(
            name: 'test_field',
            type: ColumnType::Int,
            notNull: TRUE,
          ),
        ],
        primaryKey: new PrimaryKey([]),
      ),
    );
    $this->assertSame([], $method->invoke($this->schema, 'table_without_pk_2'));

    // Test with non existing table.
    $this->assertFalse($method->invoke($this->schema, 'non_existing_table'));
  }

  /**
   * Tests the findTables() method.
   */
  public function testFindTables(): void {
    // We will be testing with three tables.
    $test_schema = Database::getConnection()->schema();

    // Create the tables.
    $table_specification = [
      'description' => 'Test table.',
      'columns' => [
        new Column(
          name: 'id',
          type: ColumnType::Int,
          default: new NullValue(),
        ),
      ],
    ];
    $table_specification['name'] = 'test_1_table';
    $test_schema->createTableFromDefinition(new Table(...$table_specification));
    $table_specification['name'] = 'test_2_table';
    $test_schema->createTableFromDefinition(new Table(...$table_specification));
    $table_specification['name'] = 'the_third_table';
    $test_schema->createTableFromDefinition(new Table(...$table_specification));

    // Check the "all tables" syntax.
    $tables = $test_schema->findTables('%');
    sort($tables);
    $expected = [
      // The 'config' table is added by
      // \Drupal\KernelTests\KernelTestBase::containerBuild().
      'config',
      'test_1_table',
      // This table uses a per-table prefix, yet it is returned as un-prefixed.
      'test_2_table',
      'the_third_table',
    ];
    $this->assertEquals($expected, $tables, 'All tables were found.');

    // Check the restrictive syntax.
    $tables = $test_schema->findTables('test_%');
    sort($tables);
    $expected = [
      'test_1_table',
      'test_2_table',
    ];
    $this->assertEquals($expected, $tables, 'Two tables were found.');

    // Check '_' and '%' wildcards.
    $table_specification['name'] = 'test3table';
    $test_schema->createTableFromDefinition(new Table(...$table_specification));
    $table_specification['name'] = 'test4';
    $test_schema->createTableFromDefinition(new Table(...$table_specification));
    $table_specification['name'] = 'testTable';
    $test_schema->createTableFromDefinition(new Table(...$table_specification));
    $table_specification['name'] = 'test';
    $test_schema->createTableFromDefinition(new Table(...$table_specification));

    $tables = $test_schema->findTables('test%');
    sort($tables);
    $expected = [
      'test',
      'test3table',
      'test4',
      'testTable',
      'test_1_table',
      'test_2_table',
    ];
    $this->assertEquals($expected, $tables, 'All "test" prefixed tables were found.');

    $tables = $test_schema->findTables('test_%');
    sort($tables);
    $expected = [
      'test3table',
      'test4',
      'testTable',
      'test_1_table',
      'test_2_table',
    ];
    $this->assertEquals($expected, $tables, 'All "/^test..*?/" tables were found.');

    $tables = $test_schema->findTables('test%table');
    sort($tables);
    $expected = [
      'test3table',
      'testTable',
      'test_1_table',
      'test_2_table',
    ];
    $this->assertEquals($expected, $tables, 'All "/^test.*?table/" tables were found.');

    $tables = $test_schema->findTables('test_%table');
    sort($tables);
    $expected = [
      'test3table',
      'test_1_table',
      'test_2_table',
    ];
    $this->assertEquals($expected, $tables, 'All "/^test..*?table/" tables were found.');

    $tables = $test_schema->findTables('test_');
    sort($tables);
    $expected = [
      'test4',
    ];
    $this->assertEquals($expected, $tables, 'All "/^test./" tables were found.');
  }

  /**
   * Tests handling of uppercase table names.
   */
  public function testUpperCaseTableName(): void {
    $table_name = 'A_UPPER_CASE_TABLE_NAME';

    // Create the table.
    $this->schema->createTableFromDefinition(
      new Table(
        name: $table_name,
        description: 'Test table.',
        columns: [
          new Column(
            name: 'id',
            type: ColumnType::Int,
            default: new NullValue(),
          ),
        ],
      ),
    );
    $this->assertTrue($this->schema->tableExists($table_name), 'Table with uppercase table name exists');
    $this->assertContains($table_name, $this->schema->findTables('%'));

    $this->assertTrue($this->schema->dropTable($table_name), 'Table with uppercase table name dropped');
    $this->assertNotContains($table_name, $this->schema->findTables('%'));
    $this->assertFalse($this->schema->tableExists($table_name));
  }

  /**
   * Tests table schema methods when a view exists.
   */
  public function testSchemaMethodsWithView(): void {
    // We will be testing with three tables.
    $test_schema = Database::getConnection()->schema();

    // Create the tables.
    $this->schema->createTableFromDefinition(
      new Table(
        name: 'test_table',
        description: 'Test table.',
        columns: [
          new Column(
            name: 'id',
            description: 'Test field.',
            type: ColumnType::Int,
            default: new NullValue(),
          ),
        ],
      ),
    );

    // Create a view to ensure it is not found.
    Database::getConnection()->query('create view {test_view} as select * from {test_table}');

    // Test \Drupal\Core\Database\Schema::tableExists().
    $this->assertTrue($this->schema->tableExists('test_table'), 'Table exists');
    $this->assertFalse($this->schema->tableExists('test_view'), 'View is not checked by tableExists() method');

    // Test \Drupal\Core\Database\Schema::findTables().
    $tables = array_values($test_schema->findTables('t%'));
    $this->assertEquals(['test_table'], $tables, 'All tables were found.');

    // Test \Drupal\Core\Database\Schema::getComment().
    $this->checkSchemaComment('Test table.', 'test_table');
    $this->checkSchemaComment('Test field.', 'test_table', 'id');
    $this->checkSchemaComment(FALSE, 'test_view');
    $this->checkSchemaComment(FALSE, 'test_view', 'id');
    $this->checkSchemaComment(FALSE, 'does_not_exist');
    $this->checkSchemaComment(FALSE, 'does_not_exist', 'id');

    // Clean up the view.
    Database::getConnection()->query('drop view {test_view}');
  }

  /**
   * Tests default values after altering table.
   */
  public function testDefaultAfterAlter(): void {
    $table_name = 'test_table';

    // Create the table.
    $this->schema->createTableFromDefinition(
      new Table(
        name: $table_name,
        description: 'Test table.',
        columns: [
          new Column(
            name: 'column1',
            type: ColumnType::Int,
            default: new NullValue(),
          ),
          new Column(
            name: 'column2',
            type: ColumnType::Varchar,
            length: 20,
            default: new NullValue(),
          ),
          new Column(
            name: 'column3',
            type: ColumnType::Int,
            default: new IntValue(200),
          ),
          new Column(
            name: 'column4',
            type: ColumnType::Float,
            default: new FloatValue(1.23),
          ),
          new Column(
            name: 'column5',
            type: ColumnType::Varchar,
            length: 20,
            default: new StringValue("'s o'clock'"),
          ),
          new Column(
            name: 'column6',
            type: ColumnType::Varchar,
            length: 20,
            default: new StringValue("o'clock"),
          ),
          new Column(
            name: 'column7',
            type: ColumnType::Varchar,
            length: 20,
            default: new StringValue('default value'),
          ),
        ],
      ),
    );

    // Insert a row and check that columns have the expected default values.
    $this->connection->insert($table_name)->fields(['column1' => 1])->execute();
    $result = $this->connection
      ->select($table_name, 't')
      ->fields('t', ['column2', 'column3', 'column4', 'column5', 'column6', 'column7'])
      ->condition('column1', 1)
      ->execute()
      ->fetchObject();
    $this->assertNull($result->column2);
    $this->assertSame('200', $result->column3);
    $this->assertSame('1.23', $result->column4);
    $this->assertSame("'s o'clock'", $result->column5);
    $this->assertSame("o'clock", $result->column6);
    $this->assertSame('default value', $result->column7);

    // Force SQLite schema to create a new table and copy data by adding a not
    // field with an initial value.
    $this->schema->addField(
      'test_table',
      'new_column',
      [
        'type' => 'varchar',
        'length' => 20,
        'not null' => TRUE,
        'description' => 'Added new column',
        'initial' => 'test',
      ]
    );

    // Test that the columns default values are still correct.
    $this->connection->insert($table_name)->fields(['column1' => 2, 'new_column' => 'value'])->execute();
    $result = $this->connection
      ->select($table_name, 't')
      ->fields('t', ['column2', 'column3', 'column4', 'column5', 'column6', 'column7'])
      ->condition('column1', 2)
      ->execute()
      ->fetchObject();
    $this->assertNull($result->column2);
    $this->assertSame('200', $result->column3);
    $this->assertSame('1.23', $result->column4);
    $this->assertSame("'s o'clock'", $result->column5);
    $this->assertSame("o'clock", $result->column6);
    $this->assertSame('default value', $result->column7);
  }

  /**
   * Tests handling with reserved keywords for naming tables, fields and more.
   */
  public function testReservedKeywordsForNaming(): void {
    // Creating a table.
    $table_name = 'select';
    $table_specification = new Table(
      name: $table_name,
      description: 'A test table with an ANSI reserved keywords for naming.',
      columns: [
        new Column(
          name: 'primary',
          description: 'Simple unique ID.',
          type: ColumnType::Int,
          notNull: TRUE,
        ),
        new Column(
          name: 'update',
          description: 'A column with reserved name.',
          type: ColumnType::Varchar,
          length: 255,
        ),
      ],
      primaryKey: new PrimaryKey(['primary']),
      uniqueKeys: [
        new UniqueKey(name: 'having', columns: ['update']),
      ],
      indexes: [
        new Index(name: 'in', columns: ['primary', 'update']),
      ],
    );
    $this->schema->createTableFromDefinition($table_specification);
    $this->assertTrue($this->schema->tableExists($table_name));

    // Finding all tables.
    $tables = $this->schema->findTables('%');
    sort($tables);
    $this->assertEquals(['config', 'select'], $tables);

    // Renaming a table.
    $table_name_new = 'from';
    $this->schema->renameTable($table_name, $table_name_new);
    $this->assertFalse($this->schema->tableExists($table_name));
    $this->assertTrue($this->schema->tableExists($table_name_new));

    // Adding a field.
    $field_name = 'delete';
    $this->schema->addField($table_name_new, $field_name, ['type' => 'int', 'not null' => TRUE]);
    $this->assertTrue($this->schema->fieldExists($table_name_new, $field_name));

    // Dropping a primary key.
    $this->schema->dropPrimaryKey($table_name_new);

    // Adding a primary key.
    $this->schema->addPrimaryKey($table_name_new, [$field_name]);

    // Check the primary key columns.
    $find_primary_key_columns = new \ReflectionMethod(get_class($this->schema), 'findPrimaryKeyColumns');
    $this->assertEquals([$field_name], $find_primary_key_columns->invoke($this->schema, $table_name_new));

    // Dropping a primary key.
    $this->schema->dropPrimaryKey($table_name_new);

    // Changing a field.
    $field_name_new = 'where';
    $this->schema->changeField($table_name_new, $field_name, $field_name_new, ['type' => 'int', 'not null' => FALSE]);
    $this->assertFalse($this->schema->fieldExists($table_name_new, $field_name));
    $this->assertTrue($this->schema->fieldExists($table_name_new, $field_name_new));

    // Adding an unique key.
    $unique_key_name = $unique_key_introspect_name = 'unique';
    $this->schema->addUniqueKey($table_name_new, $unique_key_name, [$field_name_new]);

    // Check the unique key columns.
    $introspect_index_schema = new \ReflectionMethod(get_class($this->schema), 'introspectIndexSchema');
    $this->assertEquals([$field_name_new], $introspect_index_schema->invoke($this->schema, $table_name_new)['unique keys'][$unique_key_introspect_name]);

    // Dropping an unique key.
    $this->schema->dropUniqueKey($table_name_new, $unique_key_name);

    // Dropping a field.
    $this->schema->dropField($table_name_new, $field_name_new);
    $this->assertFalse($this->schema->fieldExists($table_name_new, $field_name_new));

    // Adding an index.
    $index_name = $index_introspect_name = 'index';
    $this->schema->addIndex($table_name_new, $index_name, ['update'], $table_specification->toArray());
    $this->assertTrue($this->schema->indexExists($table_name_new, $index_name));

    // Check the index columns.
    $this->assertEquals(['update'], $introspect_index_schema->invoke($this->schema, $table_name_new)['indexes'][$index_introspect_name]);

    // Dropping an index.
    $this->schema->dropIndex($table_name_new, $index_name);
    $this->assertFalse($this->schema->indexExists($table_name_new, $index_name));

    // Dropping a table.
    $this->schema->dropTable($table_name_new);
    $this->assertFalse($this->schema->tableExists($table_name_new));
  }

  /**
   * Tests changing a field length.
   */
  public function testChangeSerialFieldLength(): void {
    $this->schema->createTableFromDefinition(new Table(
      name: 'change_serial_to_big',
      columns: [
        new Column(
          name: 'id',
          description: 'Primary Key: Unique ID.',
          type: ColumnType::Serial,
        ),
        new Column(
          name: 'text',
          description: 'A text field',
          type: ColumnType::Text,
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    ));

    // Increase the size of the field.
    $new_specification = [
      'size' => 'big',
      'type' => 'serial',
      'not null' => TRUE,
      'description' => 'Primary Key: Unique ID.',
    ];
    $this->schema->changeField('change_serial_to_big', 'id', 'id', $new_specification);
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

}
