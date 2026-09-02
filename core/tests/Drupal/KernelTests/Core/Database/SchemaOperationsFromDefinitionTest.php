<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Database\Schema;
use Drupal\Core\Database\SchemaException;
use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\ColumnSize;
use Drupal\Core\Database\SchemaDefinition\Index;
use Drupal\Core\Database\SchemaDefinition\IntValue;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Schema as SchemaDefinition;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\Core\Database\SchemaDefinition\UniqueKey;
use Drupal\Tests\Core\Database\SchemaIntrospectionTestTrait;
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->schema = $this->connection->schema();
  }

  /**
   * Tests creating a table from a definition with specialized indexes.
   *
   * By default, drivers only support regular indexes: GIN and GiST index
   * definitions are skipped, and alternative indexes fall back to their
   * regular index definition. Drivers supporting specialized indexes
   * override this method.
   */
  public function testCreateTableFromDefinition(): void {
    $tables[] = new Table(
      name: 'test_from_definition',
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
      name: 'test_from_definition',
      tables: $tables,
    );

    $this->schema->createSchemaFromDefinition($schema);

    $this->assertTrue($this->schema->tableExists('test_from_definition'));
    $this->assertTrue($this->schema->indexExists('test_from_definition', 'ages'));
    $this->assertTrue($this->schema->indexExists('test_from_definition', 'names_by_age'));
  }

  /**
   * Initial schema for ::testAddTableColumnFromDefinition.
   *
   * @return \Drupal\Core\Database\SchemaDefinition\Schema
   *   The schema.
   */
  protected function initialSchemaForAddTableColumnFromDefinition(): SchemaDefinition {
    $tables[] = new Table(
      name: 'test_add_table_column_from_definition',
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
      name: 'test_add_table_column_from_definition',
      tables: $tables,
    );
  }

  /**
   * Target schema for ::testAddTableColumnFromDefinition.
   *
   * @return \Drupal\Core\Database\SchemaDefinition\Schema
   *   The schema.
   */
  protected function targetSchemaForTestAddTableColumnFromDefinition(): SchemaDefinition {
    $tables[] = new Table(
      name: 'test_add_table_column_from_definition',
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
      name: 'test_add_table_column_from_definition',
      tables: $tables,
    );
  }

  /**
   * Tests adding a field with specialized indexes.
   *
   * The $keys_new argument of addField() may contain index definition
   * objects. By default, drivers only support regular indexes: GIN and GiST
   * index definitions are skipped, and alternative indexes fall back to
   * their regular index definition. Drivers supporting specialized indexes
   * override this method.
   */
  public function testAddTableColumnFromDefinition(): void {
    $this->schema->createSchemaFromDefinition($this->initialSchemaForAddTableColumnFromDefinition());

    $this->assertTrue($this->schema->tableExists('test_add_table_column_from_definition'));
    $this->assertFalse($this->schema->fieldExists('test_add_table_column_from_definition', 'age'));
    $method = new \ReflectionMethod($this->schema, 'findPrimaryKeyColumns');
    $this->assertEquals(['id'], $method->invoke($this->schema, 'test_add_table_column_from_definition'));
    $this->assertFalse($this->schema->indexExists('test_add_table_column_from_definition', 'ages'));
    $this->assertFalse($this->schema->indexExists('test_add_table_column_from_definition', 'names_by_age'));

    $this->schema->addTableColumnFromDefinition(
      targetSchema: $this->targetSchemaForTestAddTableColumnFromDefinition(),
      tableName: 'test_add_table_column_from_definition',
      targetColumnName: 'age',
    );

    $this->assertTrue($this->schema->tableExists('test_add_table_column_from_definition'));
    $this->assertTrue($this->schema->fieldExists('test_add_table_column_from_definition', 'age'));
    $method = new \ReflectionMethod($this->schema, 'findPrimaryKeyColumns');
    $this->assertEquals(['id'], $method->invoke($this->schema, 'test_add_table_column_from_definition'));
    $this->assertTrue($this->schema->indexExists('test_add_table_column_from_definition', 'ages'));
    $this->assertTrue($this->schema->indexExists('test_add_table_column_from_definition', 'names_by_age'));
  }

  /**
   * Initial schema for ::testChangeTableColumnFromDefinition.
   *
   * @return \Drupal\Core\Database\SchemaDefinition\Schema
   *   The schema.
   */
  protected function initialSchemaForTestChangeTableColumnFromDefinition(): SchemaDefinition {
    $tables[] = new Table(
      name: 'test_change_table_column_from_definition',
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
      name: 'test_change_table_column_from_definition',
      tables: $tables,
    );
  }

  /**
   * Target schema for ::testChangeTableColumnFromDefinition.
   *
   * @return \Drupal\Core\Database\SchemaDefinition\Schema
   *   The schema.
   */
  protected function targetSchemaForTestChangeTableColumnFromDefinition(): SchemaDefinition {
    $tables[] = new Table(
      name: 'test_change_table_column_from_definition',
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
        new UniqueKey(name: 'ages', columns: ['age_new']),
      ],
      indexes: [
        new Index(name: 'names_by_age', columns: ['name', 'age_new']),
      ],
    );

    return new SchemaDefinition(
      type: SchemaDefinitionType::Test,
      name: 'test_change_table_column_from_definition',
      tables: $tables,
    );
  }

  /**
   * Tests changing a column with specialized indexes.
   */
  public function testChangeTableColumnFromDefinition(): void {
    $this->schema->createSchemaFromDefinition($this->initialSchemaForTestChangeTableColumnFromDefinition());

    $this->assertTrue($this->schema->tableExists('test_change_table_column_from_definition'));
    $this->assertTrue($this->schema->fieldExists('test_change_table_column_from_definition', 'age'));
    $this->assertFalse($this->schema->fieldExists('test_change_table_column_from_definition', 'age_new'));
    $method = new \ReflectionMethod($this->schema, 'findPrimaryKeyColumns');
    $this->assertEquals(['id'], $method->invoke($this->schema, 'test_change_table_column_from_definition'));
    $this->assertFalse($this->schema->indexExists('test_change_table_column_from_definition', 'ages'));
    $this->assertFalse($this->schema->indexExists('test_change_table_column_from_definition', 'names_by_age'));

    $this->schema->changeTableColumnFromDefinition(
      targetSchema: $this->targetSchemaForTestChangeTableColumnFromDefinition(),
      tableName: 'test_change_table_column_from_definition',
      currentColumnName: 'age',
      targetColumnName: 'age_new',
    );

    $this->assertTrue($this->schema->tableExists('test_change_table_column_from_definition'));
    $this->assertFalse($this->schema->fieldExists('test_change_table_column_from_definition', 'age'));
    $this->assertTrue($this->schema->fieldExists('test_change_table_column_from_definition', 'age_new'));
    $method = new \ReflectionMethod($this->schema, 'findPrimaryKeyColumns');
    $this->assertEquals(['id'], $method->invoke($this->schema, 'test_change_table_column_from_definition'));
    $this->assertTrue($this->schema->indexExists('test_change_table_column_from_definition', 'ages'));
    $this->assertTrue($this->schema->indexExists('test_change_table_column_from_definition', 'names_by_age'));
  }

  /**
   * Tests changing a field length via schema definition.
   */
  public function testChangeSerialColumnLengthFromDefinition(): void {
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
              description: 'A text field',
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
      ],
    );
    $this->schema->createSchemaFromDefinition($initialSchema);

    // Increase the size of the field.
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
              description: 'A text field',
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

}
