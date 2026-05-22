<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb\Kernel;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Query\NoFieldsException;
use Drupal\KernelTests\Core\Database\DatabaseTestBase;
use Drupal\TestTools\Extension\SchemaInspector;

// cspell:ignore zazu

/**
 * Tests MongoDB table updating.
 *
 * @group MongoDB
 */
class UpdateTest extends DatabaseTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $connection = $this->container->get('database');
    $module_handler = $this->container->get('module_handler');

    // Get the schema from the table 'test'.
    $embedded_table_schema = SchemaInspector::getTablesSpecification($module_handler, 'database_test')['test'];

    // Make sure that there is a table schema for the embedded table.
    $this->assertTrue(!empty($embedded_table_schema), 'The schema for the embedded table should not be empty.');

    // Create the embedded table on the base table.
    $connection->schema()->createEmbeddedTable('test_people', 'embedded_test', $embedded_table_schema);

    // Insert row in the base table.
    $query = $connection->insert('test_people');

    $embedded_test_data = $query->embeddedTableData()
      ->fields(['name' => 'John', 'age' => 25, 'job' => 'Singer'])
      ->values(['George', 27, 'Singer'])
      ->values(['Ringo', 28, 'Drummer'])
      ->values(['Paul', 26, 'Songwriter']);

    $query->fields(['name' => 'Jasper', 'age' => '4', 'job' => 'Chatters', 'embedded_test' => $embedded_test_data])
      ->execute();

    $result = $connection->select('test_people')
      ->fields('test_people', ['name', 'age', 'job', 'embedded_test'])
      ->condition('job', 'Chatters')
      ->execute()
      ->fetchObject();

    $this->assertTrue(is_object($result), 'The result for the table query should not be empty.');
    $this->assertSame($result->name, 'Jasper', 'The value for name should be: Jasper.');
    $this->assertSame($result->age, '4', 'The value for age should be: 4.');
    $this->assertSame($result->job, 'Chatters', 'The value for job should be: Chatters.');

    // There is only four rows in the embedded table.
    $this->assertTrue(isset($result->embedded_test) && is_array($result->embedded_test) && (count($result->embedded_test) == 4), 'The embedded table has four rows.');

    $this->assertSame($result->embedded_test[0]['name'], 'John', 'The value for name should be: John.');
    $this->assertSame($result->embedded_test[0]['age'], '25', 'The value for age should be: 25.');
    $this->assertSame($result->embedded_test[0]['job'], 'Singer', 'The value for job should be: Singer.');

    $this->assertSame($result->embedded_test[1]['name'], 'George', 'The value for name should be: George.');
    $this->assertSame($result->embedded_test[1]['age'], '27', 'The value for age should be: 27.');
    $this->assertSame($result->embedded_test[1]['job'], 'Singer', 'The value for job should be: Singer.');

    $this->assertSame($result->embedded_test[2]['name'], 'Ringo', 'The value for name should be: Ringo.');
    $this->assertSame($result->embedded_test[2]['age'], '28', 'The value for age should be: 28.');
    $this->assertSame($result->embedded_test[2]['job'], 'Drummer', 'The value for job should be: Drummer.');

    $this->assertSame($result->embedded_test[3]['name'], 'Paul', 'The value for name should be: Paul.');
    $this->assertSame($result->embedded_test[3]['age'], '26', 'The value for age should be: 26.');
    $this->assertSame($result->embedded_test[3]['job'], 'Songwriter', 'The value for job should be: Songwriter.');
  }

  /**
   * Data provider for testUpdate().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - the parameters for the table update.
   *     - the expected result
   */
  public static function providerUpdate() {
    return [
      [
        ['name' => 'Zazu', 'age' => 20, 'job' => 'Makes noise'],
        ['name' => 'Zazu', 'age' => '20', 'job' => 'Makes noise'],
      ],
      [
        ['name' => 'Zazu', 'age' => '20', 'job' => 'Makes noise'],
        ['name' => 'Zazu', 'age' => '20', 'job' => 'Makes noise'],
      ],
      [
        ['name' => 'Zazu', 'age' => 20],
        ['name' => 'Zazu', 'age' => '20', 'job' => 'Undefined'],
      ],
      [
        ['name' => 'Zazu', 'job' => 'Makes noise'],
        ['name' => 'Zazu', 'age' => '0', 'job' => 'Makes noise'],
      ],
      [
        ['age' => 20, 'job' => 'Makes noise'],
        ['name' => '', 'age' => '20', 'job' => 'Makes noise'],
      ],
      [
        ['name' => 'Zazu'],
        ['name' => 'Zazu', 'age' => '0', 'job' => 'Undefined'],
      ],
      [
        ['age' => 20],
        ['name' => '', 'age' => '20', 'job' => 'Undefined'],
      ],
      [
        ['job' => 'Makes noise'],
        ['name' => '', 'age' => '0', 'job' => 'Makes noise'],
      ],
    ];
  }

  /**
   * Tests updates the embedded table data.
   *
   * @dataProvider providerUpdate
   */
  public function testUpdateEmbeddedTableData($fields, $expected) {
    $connection = $this->container->get('database');

    $query = $connection->update('test_people');
    $query->fields(['embedded_test' => $query->embeddedTableData('append')->fields($fields)])
      ->condition('job', 'Chatters')
      ->execute();

    $result = $connection->select('test_people')->fields('test_people', ['name', 'age', 'job', 'embedded_test'])->condition('job', 'Chatters')->execute()->fetchObject();

    $this->assertTrue(is_object($result), 'The result for the table query should not be empty.');
    $this->assertSame($result->name, 'Jasper', 'The value for name should be: Jasper.');
    $this->assertSame($result->age, '4', 'The value for age should be: 4.');
    $this->assertSame($result->job, 'Chatters', 'The value for job should be: Chatters.');

    // There are 5 rows in the embedded table.
    $this->assertTrue(isset($result->embedded_test) && is_array($result->embedded_test) && (count($result->embedded_test) == 5), 'The embedded table has 5 rows.');

    $embedded_table_second_row = end($result->embedded_test);
    foreach ($expected as $key => $value) {
      if (!empty($value)) {
        $this->assertSame($embedded_table_second_row[$key], $value, 'The value for ' . $key . ' is ' . $embedded_table_second_row[$key] . '. The expected value is: ' . $value . '.');
      }
    }
  }

  /**
   * Tests updates the embedded table data and deletes the old data.
   *
   * @dataProvider providerUpdate
   */
  public function testUpdateEmbeddedTableDataDeleteOldData($fields, $expected) {
    $connection = $this->container->get('database');
    $query = $connection->update('test_people');
    $query->fields(['embedded_test' => $query->embeddedTableData()->fields($fields)])
      ->condition('job', 'Chatters')
      ->execute();

    $result = $connection->select('test_people')->fields('test_people', ['name', 'age', 'job', 'embedded_test'])->condition('job', 'Chatters')->execute()->fetchObject();

    $this->assertTrue(is_object($result), 'The result for the table query should not be empty.');
    $this->assertSame($result->name, 'Jasper', 'The value for name should be: Jasper.');
    $this->assertSame($result->age, '4', 'The value for age should be: 4.');
    $this->assertSame($result->job, 'Chatters', 'The value for job should be: Chatters.');

    // There is only one row in the embedded table.
    $this->assertTrue(isset($result->embedded_test) && is_array($result->embedded_test) && (count($result->embedded_test) == 1), 'The embedded table has 5 rows.');

    $embedded_table_second_row = reset($result->embedded_test);
    foreach ($expected as $key => $value) {
      if (!empty($value)) {
        $this->assertSame($embedded_table_second_row[$key], $value, 'The value for ' . $key . ' is ' . $embedded_table_second_row[$key] . '. The expected value is: ' . $value . '.');
      }
    }
  }

  /**
   * Tests updates the embedded, embedded table data.
   */
  public function testUpdateEmbeddedEmbeddedTableData() {
    $connection = $this->container->get('database');
    $module_handler = $this->container->get('module_handler');

    // Get the schema from the table 'test_task'.
    $embedded_table_schema = SchemaInspector::getTablesSpecification($module_handler, 'database_test')['test_task'];

    // Make sure that there is a table schema for the embedded table.
    $this->assertTrue(!empty($embedded_table_schema), 'The schema for the embedded table should not be empty.');

    // Create the embedded table on the embedded table.
    $connection->schema()->createEmbeddedTable('embedded_test', 'embedded_test_task', $embedded_table_schema);

    // Update the base table.
    $query = $connection->update('test_people');

    $embedded_test_task = $query->embeddedTableData()
      ->fields(['pid' => 1, 'task' => 'eat', 'priority' => 3])
      ->values(['pid' => 1, 'task' => 'sleep', 'priority' => 4]);

    // The $-sign operator only updates the first element that matches the
    // query.
    $query->fields(['embedded_test.$.embedded_test_task' => $embedded_test_task])
      ->condition('embedded_test.job', 'Drummer')
      ->execute();

    $result = $connection->select('test_people')->fields('test_people', ['name', 'age', 'job', 'embedded_test'])->condition('job', 'Chatters')->execute()->fetchObject();

    $this->assertTrue(is_object($result), 'The result for the table query should not be empty.');
    $this->assertSame($result->name, 'Jasper', 'The value for name should be: Jasper.');
    $this->assertSame($result->age, '4', 'The value for age should be: 4.');
    $this->assertSame($result->job, 'Chatters', 'The value for job should be: Chatters.');

    // There is only one row in the embedded table.
    $this->assertTrue(isset($result->embedded_test) && is_array($result->embedded_test) && (count($result->embedded_test) == 4), 'The embedded table has 4 rows.');

    $this->assertSame($result->embedded_test[0]['name'], 'John', 'The value for name should be: John.');
    $this->assertSame($result->embedded_test[0]['age'], '25', 'The value for age should be: 25.');
    $this->assertSame($result->embedded_test[0]['job'], 'Singer', 'The value for job should be: Singer.');
    $this->assertFalse(isset($result->embedded_test[0]['embedded_test_task']) && is_array($result->embedded_test[0]['embedded_test_task']));

    $this->assertSame($result->embedded_test[1]['name'], 'George', 'The value for name should be: George.');
    $this->assertSame($result->embedded_test[1]['age'], '27', 'The value for age should be: 27.');
    $this->assertSame($result->embedded_test[1]['job'], 'Singer', 'The value for job should be: Singer.');
    $this->assertFalse(isset($result->embedded_test[1]['embedded_test_task']) && is_array($result->embedded_test[1]['embedded_test_task']));

    $this->assertSame($result->embedded_test[2]['name'], 'Ringo', 'The value for name should be: Ringo.');
    $this->assertSame($result->embedded_test[2]['age'], '28', 'The value for age should be: 28.');
    $this->assertSame($result->embedded_test[2]['job'], 'Drummer', 'The value for job should be: Drummer.');

    $this->assertTrue(isset($result->embedded_test[2]['embedded_test_task']) && is_array($result->embedded_test[2]['embedded_test_task']) && (count($result->embedded_test[2]['embedded_test_task']) == 2), 'The embedded embedded table has 2 rows.');
    $this->assertSame($result->embedded_test[2]['embedded_test_task'][0]['pid'], '1', 'The value for pid should be: 1.');
    $this->assertSame($result->embedded_test[2]['embedded_test_task'][0]['task'], 'eat', 'The value for task should be: eat.');
    $this->assertSame($result->embedded_test[2]['embedded_test_task'][0]['priority'], '3', 'The value for priority should be: 3.');
    $this->assertSame($result->embedded_test[2]['embedded_test_task'][0]['tid'], '1', 'The value for tid should be: 1.');

    $this->assertSame($result->embedded_test[2]['embedded_test_task'][1]['pid'], '1', 'The value for pid should be: 1.');
    $this->assertSame($result->embedded_test[2]['embedded_test_task'][1]['task'], 'sleep', 'The value for task should be: sleep.');
    $this->assertSame($result->embedded_test[2]['embedded_test_task'][1]['priority'], '4', 'The value for priority should be: 4.');
    $this->assertSame($result->embedded_test[2]['embedded_test_task'][1]['tid'], '2', 'The value for tid should be: 2.');

    $this->assertSame($result->embedded_test[3]['name'], 'Paul', 'The value for name should be: Paul.');
    $this->assertSame($result->embedded_test[3]['age'], '26', 'The value for age should be: 26.');
    $this->assertSame($result->embedded_test[3]['job'], 'Songwriter', 'The value for job should be: Songwriter.');
    $this->assertFalse(isset($result->embedded_test[3]['embedded_test_task']) && is_array($result->embedded_test[3]['embedded_test_task']));
  }

  /**
   * Tests updates the embedded, embedded table data.
   */
  public function testUpdateEmbeddedEmbeddedTableData2() {
    $connection = $this->container->get('database');
    $module_handler = $this->container->get('module_handler');

    // Get the schema from the table 'test_task'.
    $embedded_table_schema = SchemaInspector::getTablesSpecification($module_handler, 'database_test')['test_task'];

    // Make sure that there is a table schema for the embedded table.
    $this->assertTrue(!empty($embedded_table_schema), 'The schema for the embedded table should not be empty.');

    // Create the embedded table on the embedded table.
    $connection->schema()->createEmbeddedTable('embedded_test', 'embedded_test_task', $embedded_table_schema);

    // Update the base table.
    $query = $connection->update('test_people');

    $embedded_test_task1 = $query->embeddedTableData()
      ->fields(['pid' => 1, 'task' => 'eat', 'priority' => 3])
      ->values(['pid' => 2, 'task' => 'sleep', 'priority' => 4]);

    $embedded_test_task2 = $query->embeddedTableData()
      ->fields(['pid' => 3, 'task' => 'sing', 'priority' => 5])
      ->values(['pid' => 4, 'task' => 'code', 'priority' => 2]);

    $embedded_test = $query->embeddedTableData()
      ->fields(['name' => 'Zazu', 'age' => 20, 'job' => 'Makes noise', 'embedded_test_task' => $embedded_test_task1])
      ->values([
        'name' => 'Iago',
        'age' => 21,
        'job' => 'Makes more noise',
        'embedded_test_task' => $embedded_test_task2,
      ]);

    $query->fields(['embedded_test' => $embedded_test])
      ->condition('job', 'Chatters')
      ->execute();

    $result = $connection->select('test_people')->fields('test_people', ['name', 'age', 'job', 'embedded_test'])->condition('job', 'Chatters')->execute()->fetchObject();

    $this->assertTrue(is_object($result), 'The result for the table query should not be empty.');
    $this->assertSame($result->name, 'Jasper', 'The value for name should be: Jasper.');
    $this->assertSame($result->age, '4', 'The value for age should be: 4.');
    $this->assertSame($result->job, 'Chatters', 'The value for job should be: Chatters.');

    // There is only one row in the embedded table.
    $this->assertTrue(isset($result->embedded_test) && is_array($result->embedded_test) && (count($result->embedded_test) == 2), 'The embedded table has 2 rows.');

    $this->assertSame($result->embedded_test[0]['name'], 'Zazu', 'The value for name should be: Zazu.');
    $this->assertSame($result->embedded_test[0]['age'], '20', 'The value for age should be: 20.');
    $this->assertSame($result->embedded_test[0]['job'], 'Makes noise', 'The value for job should be: Makes noise.');

    $this->assertTrue(isset($result->embedded_test[0]['embedded_test_task']) && is_array($result->embedded_test[0]['embedded_test_task']) && (count($result->embedded_test[0]['embedded_test_task']) == 2), 'The embedded embedded table has 2 rows.');
    $this->assertSame($result->embedded_test[0]['embedded_test_task'][0]['pid'], '1', 'The value for pid should be: 1.');
    $this->assertSame($result->embedded_test[0]['embedded_test_task'][0]['task'], 'eat', 'The value for task should be: eat.');
    $this->assertSame($result->embedded_test[0]['embedded_test_task'][0]['priority'], '3', 'The value for priority should be: 3.');
    $this->assertSame($result->embedded_test[0]['embedded_test_task'][0]['tid'], '1', 'The value for tid should be: 1.');

    $this->assertSame($result->embedded_test[0]['embedded_test_task'][1]['pid'], '2', 'The value for pid should be: 2.');
    $this->assertSame($result->embedded_test[0]['embedded_test_task'][1]['task'], 'sleep', 'The value for task should be: sleep.');
    $this->assertSame($result->embedded_test[0]['embedded_test_task'][1]['priority'], '4', 'The value for priority should be: 4.');
    $this->assertSame($result->embedded_test[0]['embedded_test_task'][1]['tid'], '2', 'The value for tid should be: 2.');

    $this->assertSame($result->embedded_test[1]['name'], 'Iago', 'The value for name should be: Iago.');
    $this->assertSame($result->embedded_test[1]['age'], '21', 'The value for age should be: 21.');
    $this->assertSame($result->embedded_test[1]['job'], 'Makes more noise', 'The value for job should be: Makes more noise.');

    $this->assertTrue(isset($result->embedded_test[1]['embedded_test_task']) && is_array($result->embedded_test[1]['embedded_test_task']) && (count($result->embedded_test[1]['embedded_test_task']) == 2), 'The embedded embedded table has 2 rows.');
    $this->assertSame($result->embedded_test[1]['embedded_test_task'][0]['pid'], '3', 'The value for pid should be: 3.');
    $this->assertSame($result->embedded_test[1]['embedded_test_task'][0]['task'], 'sing', 'The value for task should be: sing.');
    $this->assertSame($result->embedded_test[1]['embedded_test_task'][0]['priority'], '5', 'The value for priority should be: 5.');
    $this->assertSame($result->embedded_test[1]['embedded_test_task'][0]['tid'], '3', 'The value for tid should be: 3.');

    $this->assertSame($result->embedded_test[1]['embedded_test_task'][1]['pid'], '4', 'The value for pid should be: 4.');
    $this->assertSame($result->embedded_test[1]['embedded_test_task'][1]['task'], 'code', 'The value for task should be: code.');
    $this->assertSame($result->embedded_test[1]['embedded_test_task'][1]['priority'], '2', 'The value for priority should be: 2.');
    $this->assertSame($result->embedded_test[1]['embedded_test_task'][1]['tid'], '4', 'The value for tid should be: 4.');
  }

  /**
   * Data provider for testDeleteCondition().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - the parameters for the table update.
   *     - the expected result
   */
  public static function providerDeleteCondition() {
    return [
      ['age', 27, '=', ['John', 'Ringo', 'Paul']],
      ['age', 27, '>', ['John', 'George', 'Paul']],
      ['age', 27, '>=', ['John', 'Paul']],
      ['age', 27, '<', ['George', 'Ringo']],
      ['age', 27, '<=', ['Ringo']],
      ['age', [25, 27], 'IN', ['Ringo', 'Paul']],
    ];
  }

  /**
   * Tests updates the embedded table data and deletes the old data.
   *
   * @dataProvider providerDeleteCondition
   */
  public function testDeleteCondition($field, $value, $operator, $expected) {
    $connection = $this->container->get('database');
    $connection->update('test_people')
      ->condition('job', 'Chatters')
      ->embeddedTableDeleteCondition('embedded_test', $field, $value, $operator)
      ->execute();

    $result = $connection->select('test_people')->fields('test_people', ['name', 'age', 'job', 'embedded_test'])->condition('job', 'Chatters')->execute()->fetchObject();

    $this->assertTrue(is_object($result), 'The result for the table query should not be empty.');
    $this->assertSame($result->name, 'Jasper', 'The value for name should be: Jasper.');
    $this->assertSame($result->age, '4', 'The value for age should be: 4.');
    $this->assertSame($result->job, 'Chatters', 'The value for job should be: Chatters.');

    $this->assertTrue(isset($result->embedded_test) && is_array($result->embedded_test) && (count($result->embedded_test) == count($expected)), 'The embedded table has the expected number of rows.');
    foreach ($expected as $key => $value) {
      $this->assertSame($result->embedded_test[$key]['name'], $value, 'The tested name is identical to the expected name.');
    }
  }

  /**
   * Tests updates the embedded table data and deletes the old data.
   */
  public function testDeleteConditionMultiple() {
    $connection = $this->container->get('database');

    $connection->update('test_people')
      ->condition('job', 'Chatters')
      ->embeddedTableDeleteCondition('embedded_test', 'age', 28, '<')
      ->embeddedTableDeleteCondition('embedded_test', 'age', 26, '>=')
      ->execute();

    $result = $connection->select('test_people')->fields('test_people', ['name', 'age', 'job', 'embedded_test'])->condition('job', 'Chatters')->execute()->fetchObject();

    $this->assertTrue(is_object($result), 'The result for the table query should not be empty.');
    $this->assertSame($result->name, 'Jasper', 'The value for name should be: Jasper.');
    $this->assertSame($result->age, '4', 'The value for age should be: 4.');
    $this->assertSame($result->job, 'Chatters', 'The value for job should be: Chatters.');

    $this->assertTrue(isset($result->embedded_test) && is_array($result->embedded_test) && (count($result->embedded_test) == 2), 'The embedded table has the expected number of rows.');
    foreach (['John', 'Ringo'] as $key => $value) {
      $this->assertSame($result->embedded_test[$key]['name'], $value, 'The tested name is identical to the expected name.');
    }
  }

  /**
   * Tests updates the embedded table data and deletes the old data.
   */
  public function testDeleteConditionOr() {
    $connection = $this->container->get('database');

    $condition = $connection->condition('OR');
    $condition->condition('age', 25)->condition('age', 27);

    $connection->update('test_people')
      ->condition('job', 'Chatters')
      ->embeddedTableDeleteCondition('embedded_test', $condition)
      ->execute();

    $result = $connection->select('test_people')->fields('test_people', ['name', 'age', 'job', 'embedded_test'])->condition('job', 'Chatters')->execute()->fetchObject();

    $this->assertTrue(is_object($result), 'The result for the table query should not be empty.');
    $this->assertSame($result->name, 'Jasper', 'The value for name should be: Jasper.');
    $this->assertSame($result->age, '4', 'The value for age should be: 4.');
    $this->assertSame($result->job, 'Chatters', 'The value for job should be: Chatters.');

    $this->assertTrue(isset($result->embedded_test) && is_array($result->embedded_test) && (count($result->embedded_test) == 2), 'The embedded table has the expected number of rows.');
    foreach (['Ringo', 'Paul'] as $key => $value) {
      $this->assertSame($result->embedded_test[$key]['name'], $value, 'The tested name is identical to the expected name.');
    }
  }

  /**
   * Tests updates the embedded table data twice.
   */
  public function testDeleteConditionAndInsert() {
    $connection = $this->container->get('database');

    // MongoDB does not allow a table variable to be changed twice in one
    // update an exception is thrown.
    $this->expectException(DatabaseExceptionWrapper::class);
    $query = $connection->update('test_people');
    $query->fields([
      'embedded_test' => $query->embeddedTableData()->fields(['name' => 'Zazu', 'age' => 20, 'job' => 'Makes noise']),
    ])
      ->condition('job', 'Chatters')
      ->embeddedTableDeleteCondition('embedded_test', 'age', 27)
      ->execute();
  }

  /**
   * Tests no fields exception on update on an embedded table.
   */
  public function testUpdateEmbeddedTableNoFieldsException() {
    $connection = $this->container->get('database');

    // The table update should result in a NoFieldsException being thrown.
    $this->expectException(NoFieldsException::class);
    $query = $connection->update('test_people');
    $query->fields(['embedded_test' => $query->embeddedTableData()->fields([])])
      ->execute();
  }

}
