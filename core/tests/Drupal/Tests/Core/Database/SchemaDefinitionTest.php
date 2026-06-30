<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\ColumnType;
use Drupal\Core\Database\SchemaDefinition\FloatValue;
use Drupal\Core\Database\SchemaDefinition\ForeignKey;
use Drupal\Core\Database\SchemaDefinition\IntValue;
use Drupal\Core\Database\SchemaDefinition\NullValue;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Schema;
use Drupal\Core\Database\SchemaDefinition\StringValue;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\Core\Database\SchemaDefinition\UniqueKey;
use Drupal\Core\Database\Exception\SchemaDefinitionException;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests SchemaDefinition Column object validations.
 */
#[Group('Database')]
#[CoversClass(Schema::class)]
#[CoversClass(Table::class)]
#[CoversClass(Column::class)]
class SchemaDefinitionTest extends UnitTestCase {

  /**
   * Tests Schema.
   */
  public function testSchemaMethods(): void {
    $schemaDefinition = new Schema(
      name: 'test_foo_bar',
      tables: [
        new Table(
          name: 'foo',
          description: 'Foo table for the database unit tests.',
          columns: [
            new Column(
              name: 'id',
              type: ColumnType::Serial,
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
        new Table(
          name: 'bar',
          description: 'Bar table for the database unit tests.',
          columns: [
            new Column(
              name: 'id',
              type: ColumnType::Serial,
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
      ],
    );

    // Check ::tableNames().
    $this->assertSame(['foo', 'bar'], $schemaDefinition->tableNames());

    // Check ::getTableDefinition().
    $tableDefinition = $schemaDefinition->getTableDefinition('foo');
    $this->assertInstanceOf(Table::class, $tableDefinition);
    $this->assertSame('foo', $tableDefinition->name);
    $this->assertSame('Foo table for the database unit tests.', $tableDefinition->description);

    // Check iterability of the Schema object.
    $expected = ['foo', 'bar'];
    foreach ($schemaDefinition as $tableDefinition) {
      $this->assertSame(array_shift($expected), $tableDefinition->name);
    }
  }

  /**
   * Tests passing a non Table array member to Schema::$tables.
   */
  public function testInvalidTableInSchema(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('All members of the \'tables\' argument of a Schema must be Table objects');
    $table = new Schema(
      name: 'test',
      tables: [
        'foo' => [
          'description' => 'Foo table for the database unit tests.',
          'fields' => [
            'id' => [
              'type' => 'serial',
              'unsigned' => TRUE,
              'not null' => TRUE,
            ],
          ],
          'primary key' => ['id'],
        ],
      ],
    );
    $this->assertInstanceOf(Table::class, $table);
  }

  /**
   * Tests passing a non Column array member to Table::$columns.
   */
  public function testInvalidColumnInTable(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('All members of the \'columns\' argument of the \'test\' Table must be Column objects');
    $table = new Table(
      name: 'test',
      columns: [
        'name' => [
          'description' => "A person's name",
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
          'binary' => TRUE,
        ],
      ],
      primaryKey: new PrimaryKey(['id']),
    );
    $this->assertInstanceOf(Table::class, $table);
  }

  /**
   * Tests passing a non Column array member to Table::$columns.
   */
  public function testInvalidForeignKeyForMismatchingColumnCount(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('Mismatching count of columns for the \'user_id\' foreign key');
    $table = new Table(
      name: 'test',
      description: 'Basic test table for the database unit tests.',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Serial,
        ),
        new Column(
          name: 'name',
          description: "A person's name",
          type: ColumnType::VarcharAscii,
          length: 255,
          notNull: TRUE,
          default: new StringValue(''),
          binary: TRUE,
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
      foreignKeys: [
        new ForeignKey(
          name: 'user_id',
          foreignTable: 'test_users',
          columns: ['id', 'name'],
          foreignColumns: ['id'],
        ),
      ],
    );
    $this->assertInstanceOf(Table::class, $table);
  }

  /**
   * Tests Serial column.
   */
  public function testInvalidSerialForDefault(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('Cannot set a Drupal\Core\Database\SchemaDefinition\IntValue default for column \'id\'');
    new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Serial,
          default: new IntValue(1),
        ),
        new Column(
          name: 'foo',
          type: ColumnType::VarcharAscii,
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
  }

  /**
   * Tests Serial column.
   */
  public function testInvalidSerialForNotNull(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('Cannot set \'notNull\' for serial column \'id\'');
    new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Serial,
          notNull: TRUE,
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
  }

  /**
   * Tests Serial column.
   */
  public function testInvalidSerialForUnsigned(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('Cannot set \'unsigned\' for serial column \'id\'');
    new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Serial,
          unsigned: TRUE,
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
  }

  /**
   * Tests passing a null default when notNull: TRUE is specified.
   */
  public function testNullDefaultForNotNullArgument(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('Cannot set a null default for column \'id\': notNull is true');
    new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Int,
          notNull: TRUE,
          default: new NullValue(),
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
  }

  /**
   * Tests passing a float default to an int column.
   */
  public function testFloatDefaultForIntColumn(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('Cannot set a Drupal\Core\Database\SchemaDefinition\FloatValue default for column \'id\'');
    new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Int,
          default: new FloatValue(10),
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
  }

  /**
   * Tests passing a int default to an int column.
   */
  public function testIntDefaultForIntColumn(): void {
    $table = new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Int,
          default: new IntValue(10),
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
    $this->assertInstanceOf(Table::class, $table);
  }

  /**
   * Tests passing a null default to an int column.
   */
  public function testNullDefaultForIntColumn(): void {
    $table = new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Int,
          default: new NullValue(),
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
    $this->assertInstanceOf(Table::class, $table);
  }

  /**
   * Tests passing a string default to an int column.
   */
  public function testStringDefaultForIntColumn(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('Cannot set a Drupal\Core\Database\SchemaDefinition\StringValue default for column \'id\'');
    new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Int,
          default: new StringValue('foo'),
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
  }

  /**
   * Tests passing a float default to a text column.
   */
  public function testFloatDefaultForTextColumn(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('Cannot set a Drupal\Core\Database\SchemaDefinition\FloatValue default for column \'id\'');
    new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Text,
          default: new FloatValue(10),
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
  }

  /**
   * Tests passing a int default to a text column.
   */
  public function testIntDefaultForTextColumn(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('Cannot set a Drupal\Core\Database\SchemaDefinition\IntValue default for column \'id\'');
    $table = new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Text,
          default: new IntValue(10),
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
    $this->assertInstanceOf(Table::class, $table);
  }

  /**
   * Tests passing a null default to a text column.
   */
  public function testNullDefaultForTextColumn(): void {
    $table = new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Text,
          default: new NullValue(),
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
    $this->assertInstanceOf(Table::class, $table);
  }

  /**
   * Tests passing a string default to a text column.
   */
  public function testStringDefaultForTextColumn(): void {
    $table = new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Text,
          default: new StringValue('foo'),
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
    $this->assertInstanceOf(Table::class, $table);
  }

  /**
   * Tests passing unsigned to a non-numeric column.
   */
  public function testInvalidColumnTypeForUnsigned(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('Cannot set \'unsigned\' for text column \'id\'');
    $table = new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Text,
          unsigned: TRUE,
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
    $this->assertInstanceOf(Table::class, $table);
  }

  /**
   * Tests passing length to a non-textual column.
   */
  public function testInvalidColumnTypeForLength(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('Cannot set \'length\' for int column \'id\'');
    $table = new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Int,
          length: 20,
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
    $this->assertInstanceOf(Table::class, $table);
  }

  /**
   * Tests passing binary to a non-textual column.
   */
  public function testInvalidColumnTypeForBinary(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('Cannot set \'binary\' for int column \'id\'');
    $table = new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Int,
          binary: TRUE,
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
    $this->assertInstanceOf(Table::class, $table);
  }

  /**
   * Tests passing a scale to a non-numeric column.
   */
  public function testInvalidColumnTypeForScale(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('Cannot set \'scale\' for text column \'id\'');
    $table = new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Text,
          scale: 4,
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
    $this->assertInstanceOf(Table::class, $table);
  }

  /**
   * Tests passing a precision to a non-numeric column.
   */
  public function testInvalidColumnTypeForPrecision(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('Cannot set \'precision\' for text column \'id\'');
    $table = new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Text,
          precision: 2,
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
    $this->assertInstanceOf(Table::class, $table);
  }

  /**
   * Tests passing a serialize to a non-text/blog column.
   */
  public function testInvalidColumnTypeForSerialize(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('Cannot set \'serialize\' for int column \'id\'');
    $table = new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Int,
          serialize: TRUE,
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
    );
    $this->assertInstanceOf(Table::class, $table);
  }

  /**
   * Tests passing limited length columns to primary key.
   */
  public function testInvalidPrimaryKeyForPartialLengthColumn(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('Limited length columns not allowed in this context');
    $table = new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Text,
          length: 40,
          default: new StringValue('foo'),
        ),
      ],
      primaryKey: new PrimaryKey([['id', 10]]),
    );
    $this->assertInstanceOf(Table::class, $table);
  }

  /**
   * Tests passing limited length columns to an unique key.
   */
  public function testInvalidUniqueKeyForPartialLengthColumn(): void {
    $this->expectException(SchemaDefinitionException::class);
    $this->expectExceptionMessage('Limited length columns not allowed in this context');
    $table = new Table(
      name: 'test',
      columns: [
        new Column(
          name: 'id',
          type: ColumnType::Text,
          length: 40,
          default: new StringValue('foo'),
        ),
      ],
      primaryKey: new PrimaryKey(['id']),
      uniqueKeys: [
        new UniqueKey(
          name: 'invalid',
          columns: [['id', 10]],
        ),
      ],
    );
    $this->assertInstanceOf(Table::class, $table);
  }

}
