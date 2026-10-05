<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\ColumnSize;
use Drupal\Core\Database\SchemaDefinition\Index;
use Drupal\Core\Database\SchemaDefinition\IntValue;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Schema;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionInterface;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;
use Drupal\Core\Database\SchemaDefinition\StringValue;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\Core\Database\SchemaDefinition\UniqueKey;
use Drupal\Core\Database\Utility\SchemaDefinitionDiffer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Schema differ.
 *
 * @phpstan-type-import DiffEntry from \Drupal\Core\Database\Utility\SchemaDefinitionDiffer
 */
#[Group('Database')]
#[CoversClass(SchemaDefinitionDiffer::class)]
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
          uniqueKeys: [
            new UniqueKey(name: 'bar', columns: ['id', 'month']),
          ],
          indexes: [
            new Index(name: 'swap', columns: ['id', 'month']),
          ],
          dbSpecificExtra: [
            'mysql' => [
              'mysql_engine' => 'InnoDB',
              'mysql_character_set' => 'utf8mb4',
            ],
            'zoo' => ['lion', 'giraffe'],
          ],
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
          indexes: [
            new Index(name: 'swap', columns: ['month', 'id', 'surname']),
          ],
          dbSpecificExtra: [
            'mysql' => [
              'mysql_engine' => 'InnoDB',
              'ziggy' => 'stardust',
              'mysql_character_set' => 'utf8mb4',
            ],
            'zoo' => ['lion', 'turtle', 'giraffe'],
          ],
        ),
      ],
    );

    $this->assertEquals(
      [
        "tables[foo].columns[id].type" => 'Changed',
        "tables[foo].columns[month].size" => 'Unset',
        "tables[foo].columns[month].default" => 'Set',
        "tables[foo].columns[surname]" => 'Added',
        "tables[foo].indexes[swap].columns[0].name" => 'Changed',
        "tables[foo].indexes[swap].columns[1].name" => 'Changed',
        "tables[foo].indexes[swap].columns[2]" => 'Added',
        "tables[foo].primaryKey.columns[1]" => 'Added',
        'tables[foo].uniqueKeys[bar]' => 'Removed',
        'tables[foo].dbSpecificExtra[mysql][ziggy]' => 'Added',
        'tables[foo].dbSpecificExtra[zoo][1]' => 'Changed',
        'tables[foo].dbSpecificExtra[zoo][2]' => 'Added'
      ],
      $this->compactDiff(SchemaDefinitionDiffer::diff($schemaA, $schemaB)),
    );

    $this->assertEquals(
      [
        "columns[id].type" => 'Changed',
        "columns[month].size" => 'Unset',
        "columns[month].default" => 'Set',
        "columns[surname]" => 'Added',
        "indexes[swap].columns[0].name" => 'Changed',
        "indexes[swap].columns[1].name" => 'Changed',
        "indexes[swap].columns[2]" => 'Added',
        "primaryKey.columns[1]" => 'Added',
        'uniqueKeys[bar]' => 'Removed',
        'dbSpecificExtra[mysql][ziggy]' => 'Added',
        'dbSpecificExtra[zoo][1]' => 'Changed',
        'dbSpecificExtra[zoo][2]' => 'Added'
      ],
      $this->compactDiff(SchemaDefinitionDiffer::diff($schemaA->getTableDefinition('foo'), $schemaB->getTableDefinition('foo'))),
    );

    $this->assertEquals(
      [
        "columns[1]" => 'Added',
      ],
      $this->compactDiff(SchemaDefinitionDiffer::diff($schemaA->getTableDefinition('foo')->primaryKey, $schemaB->getTableDefinition('foo')->primaryKey)),
    );
  }

  /**
   * Returns a compact diff between two SchemaDefinitionInterface objects.
   *
   * @param array<string,DiffEntry>
   *   A SchemaDefinitionInterface diff.
   *
   * @return array<string,string>
   *   The compacted diff entries, exposing path as key and the diff type as
   *   value.
   */
  protected function compactDiff(array $diff): array {
    return array_map(fn ($value): string => $value['type']->name, $diff);
  }

}
