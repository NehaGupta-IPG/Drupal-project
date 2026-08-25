<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that a JSON condition does not change how other parameters compare.
 *
 * A JsonCondition casts the extracted JSON value, so every parameter in the
 * statement stays bound as a string. Conditions on other columns in the same
 * query therefore match the same way as they would without the JSON condition.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class JsonConditionParameterBindingTest extends DatabaseTestBase {

  /**
   * Tests that an integer value matches a string column literally.
   *
   * Only '10' matches. If the value were bound as an integer, MySQL and MariaDB
   * would compare numerically and ' 10' and '10.0' would match as well.
   */
  public function testIntegerConditionOnStringColumn(): void {
    $this->connection->insert('test')
      ->fields(['name', 'age', 'job'])
      ->values(['name' => '10', 'age' => 60, 'job' => 'Json'])
      ->values(['name' => ' 10', 'age' => 61, 'job' => 'Json'])
      ->values(['name' => '10.0', 'age' => 62, 'job' => 'Json'])
      ->execute();

    $query = $this->connection->select('test', 't');
    $query->addField('t', 'age');
    $query->join('json', 'j', '1 = 1');
    // Matches a single row of the joined table.
    $query->jsonCondition('j.test_field', '$.number', 5, '=');
    $query->condition('t.name', 10);
    $query->condition('t.job', 'Json');

    $this->assertEquals([60], $query->execute()->fetchCol());
  }

  /**
   * Tests that comparing an integer value to a string column is supported.
   *
   * No row matches, so every backend returns an empty result. An integer-bound
   * parameter would fail on PostgreSQL with SQLSTATE 42883, because there is no
   * character varying = integer operator.
   */
  public function testIntegerConditionOnStringColumnExecutes(): void {
    $query = $this->connection->select('test', 't');
    $query->addField('t', 'name');
    $query->join('json', 'j', '1 = 1');
    // Matches a single row of the joined table.
    $query->jsonCondition('j.test_field', '$.number', 5, '=');
    $query->condition('t.name', 26);

    $this->assertSame([], $query->execute()->fetchCol());
  }

  /**
   * Tests that a float value matches a string column literally.
   *
   * Only '3.14' matches, on every driver. A float typed at bind time would be
   * compared numerically by mysqli and as a string by the PDO drivers.
   */
  public function testFloatConditionOnStringColumn(): void {
    $this->connection->insert('test')
      ->fields(['name', 'age', 'job'])
      ->values(['name' => '3.14', 'age' => 70, 'job' => 'Float'])
      ->values(['name' => ' 3.14', 'age' => 71, 'job' => 'Float'])
      ->values(['name' => '3.140', 'age' => 72, 'job' => 'Float'])
      ->execute();

    $query = $this->connection->select('test', 't');
    $query->addField('t', 'age');
    $query->join('json', 'j', '1 = 1');
    // Matches a single row of the joined table.
    $query->jsonCondition('j.test_field', '$.number', 5, '=');
    $query->condition('t.name', 3.14);
    $query->condition('t.job', 'Float');

    $this->assertEquals([70], $query->execute()->fetchCol());
  }

  /**
   * Tests that a FALSE condition value matches an integer column value of 0.
   *
   * Connection::query() normalizes the boolean to 0, which matches on every
   * backend. Bound as a string, FALSE becomes '' and does not match 0 on
   * SQLite.
   *
   * @see \Drupal\Core\Database\Connection::query()
   */
  public function testBooleanConditionOnIntegerColumn(): void {
    $this->connection->insert('json')
      ->fields(['id', 'status', 'test_field'])
      ->values(['id' => 12, 'status' => 0, 'test_field' => '{"number": 99}'])
      ->execute();

    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonCondition('j.test_field', '$.number', 99, '=');
    $query->condition('status', FALSE);

    $this->assertEquals([12], $query->execute()->fetchCol());
  }

}
