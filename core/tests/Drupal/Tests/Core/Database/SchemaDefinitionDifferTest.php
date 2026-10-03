<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\ColumnSize;
use Drupal\Core\Database\SchemaDefinition\IntValue;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Schema;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionInterface;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;
use Drupal\Core\Database\SchemaDefinition\StringValue;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\Core\Database\Utility\SchemaDefinitionDiffer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Schema differ.
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
        "tables[foo].columns[id].type" => 'Changed',
        "tables[foo].columns[month].size" => 'Changed',
        "tables[foo].columns[month].default" => 'Changed',
        "tables[foo].columns[surname]" => 'Added',
        "tables[foo].primaryKey.columns[month]" => 'Added',
      ],
      $this->compactDiff($schemaA, $schemaB),
    );

    $this->assertEquals(
      [
        "columns[id].type" => 'Changed',
        "columns[month].size" => 'Changed',
        "columns[month].default" => 'Changed',
        "columns[surname]" => 'Added',
        "primaryKey.columns[month]" => 'Added',
      ],
      $this->compactDiff($schemaA->getTableDefinition('foo'), $schemaB->getTableDefinition('foo')),
    );

    $this->assertEquals(
      [
        "columns[month]" => 'Added',
      ],
      $this->compactDiff($schemaA->getTableDefinition('foo')->primaryKey, $schemaB->getTableDefinition('foo')->primaryKey),
    );
  }

  /**
   * Returns a compact diff between two SchemaDefinitionInterface objects.
   *
   * @param SchemaDefinitionInterface $old
   *   A SchemaDefinitionInterface object to compare.
   * @param SchemaDefinitionInterface $new
   *   A SchemaDefinitionInterface object to compare.
   *
   * @return array<string,string>
   *   The compacted diff entries, exposing path as key and the diff type as
   *   value.
   */
  protected function compactDiff(SchemaDefinitionInterface $a, SchemaDefinitionInterface $b): array {
    $diff = SchemaDefinitionDiffer::diff($a, $b);
    return array_map(fn ($value): string => $value['type']->name, $diff);
  }
}
