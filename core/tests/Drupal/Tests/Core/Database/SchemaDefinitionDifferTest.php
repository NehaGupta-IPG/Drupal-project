<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\ColumnSize;
use Drupal\Core\Database\SchemaDefinition\Differ;
use Drupal\Core\Database\SchemaDefinition\IntValue;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Schema;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;
use Drupal\Core\Database\SchemaDefinition\StringValue;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests VersionedSchema definition object validations.
 */
#[Group('Database')]
#[CoversClass(Differ::class)]
class SchemaDefinitionDifferTest extends UnitTestCase {

  /**
   * Basic test of the object methods.
   */
  public function testDiffOfSchemaObjects(): void {
    $schemaA = new Schema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      tables: [
        new Table(
          name: 'foo',
          columns: [
            Column::int(
              name: 'id',
            ),
            Column::int(
              name: 'month',
              size: ColumnSize::Big,
            ),
          ],
          primaryKey: new PrimaryKey(['id']),
        ),
      ],
    );

    $schemaB = new Schema(
      type: SchemaDefinitionType::Test,
      name: 'foo',
      tables: [
        new Table(
          name: 'foo',
          columns: [
            Column::serial(
              name: 'id',
            ),
            Column::varcharAscii(
              name: 'surname',
              description: "A person's surname",
              length: 255,
              notNull: TRUE,
              default: new StringValue(''),
              binary: TRUE,
            ),
            Column::int(
              name: 'month',
              default: new IntValue(2),
            ),
          ],
          primaryKey: new PrimaryKey(['id', 'month']),
        ),
      ],
    );

    $this->assertEquals(
      [
        "tables.foo.columns.id.type",
        "tables.foo.columns.month.size",
        "tables.foo.columns.month.default",
        "tables.foo.columns.surname",
        "tables.foo.primaryKey.columns.[1]month",
      ],
      array_keys(Differ::diff($schemaA, $schemaB))
    );

    $this->assertEquals(
      [
        "columns.id.type",
        "columns.month.size",
        "columns.month.default",
        "columns.surname",
        "primaryKey.columns.[1]month",
      ],
      array_keys(Differ::diff($schemaA->getTableDefinition('foo'), $schemaB->getTableDefinition('foo'))),
    );

    $this->assertEquals(
      [
        "columns.[1]month",
      ],
      array_keys(Differ::diff($schemaA->getTableDefinition('foo')->primaryKey, $schemaB->getTableDefinition('foo')->primaryKey)),
    );
  }

}
