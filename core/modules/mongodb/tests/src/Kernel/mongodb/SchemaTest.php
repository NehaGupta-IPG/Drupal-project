<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb\Kernel\mongodb;

use Drupal\KernelTests\Core\Database\DriverSpecificSchemaTestBase;

/**
 * Tests schema API for the MySQL driver.
 *
 * @group Database
 */
class SchemaTest extends DriverSpecificSchemaTestBase {

  /**
   * {@inheritdoc}
   */
  public function checkSchemaComment(string $description, string $table, ?string $column = NULL): void {
    $comment = $this->schema->getComment($table, $column);
    $this->assertSame($description, $comment, 'The comment matches the schema description.');
  }

  /**
   * {@inheritdoc}
   */
  public function testTableWithSpecificDataType(): void {
    $table_specification = [
      'description' => 'Schema table description.',
      'fields' => [
        'timestamp'  => [
          'mongodb_type' => 'timestamp',
          'not null' => FALSE,
          'default' => NULL,
        ],
      ],
    ];
    $this->schema->createTable('test_timestamp', $table_specification);
    $this->assertTrue($this->schema->tableExists('test_timestamp'));
  }

  /**
   * {@inheritdoc}
   */
  public function testSchemaAddFieldDefaultInitial(): void {
    // @todo This test should pass.
    $this->markTestSkipped();
  }

  /**
   * Returns the column names used by the indexes of a table.
   *
   * @param string $table_name
   *   A table name.
   * @param string $index_type
   *   The type of the index. Can be one of 'index', 'unique' or 'primary'.
   *
   * @return array
   *   A multi-dimensional array containing the column names for each index of
   *   the given type.
   */
  protected function getIndexColumnNames($table_name, $index_type) {
    assert(in_array($index_type, ['index', 'unique', 'primary'], TRUE));

    $table_schema = $this->connection->tableInformation()->getTable($table_name) ?? ['primary key' => []];

    // Filter the indexes by type.
    if ($index_type === 'primary') {
      $indexes = [$table_schema['primary key']];
    }
    elseif ($index_type === 'unique') {
      $indexes = array_values($table_schema['unique keys']);
    }
    else {
      $indexes = array_values($table_schema['indexes']);
    }

    return $indexes;
  }

}
