<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb\Kernel;

use Drupal\KernelTests\Core\Database\DatabaseTestBase;
use Drupal\TestTools\Extension\SchemaInspector;

// cspell:ignore zazu

/**
 * Tests MongoDB table inserting.
 *
 * @group MongoDB
 */
class InsertTest extends DatabaseTestBase {

  /**
   * Data provider for testInsert().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - the table name to be used.
   *     - the parameters for the table insert.
   *     - the expected result
   */
  public static function providerInsert() {
    return [
      [
        'test',
        ['name' => 'Zazu the parrot', 'age' => 20, 'job' => 'Makes noise'],
        ['name' => 'Zazu the parrot', 'age' => '20', 'job' => 'Makes noise'],
      ],
      [
        'test',
        ['name' => 'Zazu the parrot', 'age' => '20', 'job' => 'Makes noise'],
        ['name' => 'Zazu the parrot', 'age' => '20', 'job' => 'Makes noise'],
      ],
      [
        'test',
        ['name' => 'Zazu the parrot', 'age' => 20],
        ['name' => 'Zazu the parrot', 'age' => '20', 'job' => 'Undefined'],
      ],
      [
        'test',
        ['name' => 'Zazu the parrot', 'job' => 'Makes noise'],
        ['name' => 'Zazu the parrot', 'age' => '0', 'job' => 'Makes noise'],
      ],
      [
        'test',
        ['age' => 20, 'job' => 'Makes noise'],
        ['name' => '', 'age' => '20', 'job' => 'Makes noise'],
      ],
      [
        'test',
        ['name' => 'Zazu the parrot'],
        ['name' => 'Zazu the parrot', 'age' => '0', 'job' => 'Undefined'],
      ],
      [
        'test',
        ['age' => 20],
        ['name' => '', 'age' => '20', 'job' => 'Undefined'],
      ],
      [
        'test',
        ['job' => 'Makes noise'],
        ['name' => '', 'age' => '0', 'job' => 'Makes noise'],
      ],
      [
        'test_null',
        ['name' => 'Zazu the parrot', 'age' => 20],
        ['name' => 'Zazu the parrot', 'age' => '20'],
      ],
      [
        'test_null',
        ['name' => 'Zazu the parrot'],
        ['name' => 'Zazu the parrot', 'age' => '0'],
      ],
      [
        'test_null',
        ['age' => 20],
        ['name' => '', 'age' => '20'],
      ],
      [
        'test_null',
        ['name' => NULL, 'age' => 20],
        ['name' => NULL, 'age' => '20'],
      ],
      [
        'test_null',
        ['name' => 'Zazu the parrot', 'age' => NULL],
        ['name' => 'Zazu the parrot', 'age' => NULL],
      ],
      [
        'test_null',
        ['name' => NULL, 'age' => NULL],
        ['name' => NULL, 'age' => NULL],
      ],
      [
        'test_null',
        ['name' => NULL],
        ['name' => NULL, 'age' => '0'],
      ],
      [
        'test_null',
        ['age' => NULL],
        ['name' => '', 'age' => NULL],
      ],
    ];
  }

  /**
   * Tests inserts in a base table with no embedded tables.
   *
   * @dataProvider providerInsert
   */
  public function testInsert($table, $fields, $expected) {
    $connection = $this->container->get('database');

    $last_inserted_id = $connection->insert($table)->fields($fields)->execute();
    $result = $connection->select($table)->fields($table, array_keys($expected))->condition('id', intval($last_inserted_id))->execute()->fetchObject();
    foreach ($expected as $key => $value) {
      $this->assertSame($result->{$key}, $value, 'The value for ' . $key . ' is ' . $result->{$key} . '. The expected value is: ' . $value . '.');
    }
  }

  /**
   * Tests inserts in a base table and an embedded table.
   *
   * @dataProvider providerInsert
   */
  public function testEmbeddedInsert($table_name, $fields, $expected) {
    $connection = $this->container->get('database');
    $module_handler = $this->container->get('module_handler');

    // Get the schema from the table.
    $base_table_name = 'test_people';
    $embedded_table_name = 'embedded_' . $table_name;
    $embedded_table_schema = SchemaInspector::getTablesSpecification($module_handler, 'database_test')[$table_name];

    // Make sure that there is a table schema for the embedded table.
    $this->assertTrue(!empty($embedded_table_schema), 'The schema for the embedded table should not be empty.');

    // Create the embedded table on the base table.
    $connection->schema()->createEmbeddedTable($base_table_name, $embedded_table_name, $embedded_table_schema);

    // Insert row in the base table.
    $connection->insert($base_table_name)
      ->fields(['name' => 'Scuttle', 'age' => '11', 'job' => 'Flaps his wings'])
      ->execute();

    $field_keys = ['name', 'age', 'job', $embedded_table_name];
    $result = $connection->select($base_table_name)->fields($base_table_name, $field_keys)->condition('job', 'Flaps his wings')->execute()->fetchObject();
    $this->assertTrue(is_object($result), 'The result for the table query should not be empty.');
    $this->assertSame($result->name, 'Scuttle', 'The value for name should be: Scuttle.');
    $this->assertSame($result->age, '11', 'The value for age should be: 11.');
    $this->assertSame($result->job, 'Flaps his wings', 'The value for job should be: Flaps his wings.');
    $this->assertSame($result->{$embedded_table_name}, NULL, 'The value for embedded table should be: NULL.');

    // Insert row in the base fields and in the embedded table.
    $query = $connection->insert($base_table_name);
    $embedded_table_data = $query->embeddedTableData()->fields($fields);
    $query->fields(['name' => 'Jasper', 'age' => '4', 'job' => 'Chatters', $embedded_table_name => $embedded_table_data])->execute();

    $result = $connection->select($base_table_name)->fields($base_table_name, $field_keys)->condition('job', 'Chatters')->execute()->fetchObject();

    $this->assertTrue(is_object($result), 'The result for the table query should not be empty.');
    $this->assertSame($result->name, 'Jasper', 'The value for name should be: Jasper.');
    $this->assertSame($result->age, '4', 'The value for age should be: 4.');
    $this->assertSame($result->job, 'Chatters', 'The value for job should be: Chatters.');

    // There is only one row in the embedded table.
    $this->assertTrue(isset($result->{$embedded_table_name}) && is_array($result->{$embedded_table_name}) && (count($result->{$embedded_table_name}) == 1), 'The embedded table has one row.');
    $embedded_table_row = reset($result->{$embedded_table_name});
    foreach ($expected as $key => $value) {
      if (!empty($value)) {
        $this->assertSame($embedded_table_row[$key], $value, 'The value for ' . $key . ' is ' . $embedded_table_row[$key] . '. The expected value is: ' . $value . '.');
      }
    }
  }

  /**
   * Tests inserts in a base table and an embedded table.
   *
   * @dataProvider providerInsert
   */
  public function testEmbeddedEmbeddedInsert($table_name, $fields, $expected) {
    $connection = $this->container->get('database');
    $module_handler = $this->container->get('module_handler');
    $base_table_name = 'test_people';
    $parent_table_name = 'embedded_test_task';
    $parent_table_schema = SchemaInspector::getTablesSpecification($module_handler, 'database_test')['test_task'];

    // Create the embedded table on the base table.
    $connection->schema()->createEmbeddedTable($base_table_name, $parent_table_name, $parent_table_schema);

    $embedded_table_name = 'embedded_' . $table_name;
    $embedded_table_schema = SchemaInspector::getTablesSpecification($module_handler, 'database_test')[$table_name];

    // Make sure that there is a table schema for the embedded table.
    $this->assertTrue(!empty($embedded_table_schema), 'The schema for the embedded table should not be empty.');

    // Create the embedded table on the base table.
    $connection->schema()->createEmbeddedTable($parent_table_name, $embedded_table_name, $embedded_table_schema);

    // Insert row in the base fields and in the embedded table.
    $query = $connection->insert($base_table_name);
    $parent_table_data = $query->embeddedTableData()->fields(['pid' => 1, 'task' => 'eat', 'priority' => 3]);
    $query->fields([
      'name' => 'Scuttle',
      'age' => '11',
      'job' => 'Flaps his wings',
      $parent_table_name => $parent_table_data,
    ])->execute();

    $field_keys = ['name', 'age', 'job', 'embedded_test_task'];
    $result = $connection->select($base_table_name)->fields($base_table_name, $field_keys)->condition('job', 'Flaps his wings')->execute()->fetchObject();

    $this->assertTrue(is_object($result), 'The result for the table query should not be empty.');
    $this->assertSame($result->name, 'Scuttle', 'The value for name should be: Scuttle.');
    $this->assertSame($result->age, '11', 'The value for age should be: 11.');
    $this->assertSame($result->job, 'Flaps his wings', 'The value for job should be: Flaps his wings.');
    $parent_table_row = reset($result->{$parent_table_name});
    $this->assertSame($parent_table_row['pid'], '1', 'The value for pid should be: 1.');
    $this->assertSame($parent_table_row['task'], 'eat', 'The value for task should be: eat.');
    $this->assertSame($parent_table_row['priority'], '3', 'The value for priority should be: 3.');

    // Insert row in the base fields and in the embedded table.
    $query = $connection->insert($base_table_name);
    $embedded_table_data = $query->embeddedTableData()->fields($fields);
    $parent_table_data = $query->embeddedTableData()->fields([
      'pid' => 2,
      'task' => 'sleep',
      'priority' => 4,
      $embedded_table_name => $embedded_table_data,
    ]);
    $query->fields(['name' => 'Jasper', 'age' => '4', 'job' => 'Chatters', $parent_table_name => $parent_table_data])->execute();

    $field_keys = ['name', 'age', 'job', 'embedded_test_task'];
    $result = $connection->select($base_table_name)->fields($base_table_name, $field_keys)->condition('job', 'Chatters')->execute()->fetchObject();

    $this->assertTrue(is_object($result), 'The result for the table query should not be empty.');
    $this->assertSame($result->name, 'Jasper', 'The value for name should be: Jasper.');
    $this->assertSame($result->age, '4', 'The value for age should be: 4.');
    $this->assertSame($result->job, 'Chatters', 'The value for job should be: Chatters.');
    $parent_table_row = reset($result->{$parent_table_name});
    $this->assertSame($parent_table_row['pid'], '2', 'The value for pid should be: 2.');
    $this->assertSame($parent_table_row['task'], 'sleep', 'The value for task should be: sleep.');
    $this->assertSame($parent_table_row['priority'], '4', 'The value for priority should be: 4.');

    // There is only one row in the embedded table.
    $this->assertTrue(isset($parent_table_row[$embedded_table_name]) && is_array($parent_table_row[$embedded_table_name]) && (count($parent_table_row[$embedded_table_name]) == 1), 'The embedded table has one row.');
    $embedded_table_row = reset($parent_table_row[$embedded_table_name]);
    foreach ($expected as $key => $value) {
      if (!empty($value)) {
        $this->assertSame($embedded_table_row[$key], $value, 'The value for ' . $key . ' is ' . $embedded_table_row[$key] . '. The expected value is: ' . $value . '.');
      }
    }
  }

}
