<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests a float condition value against a string column.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class StrictParamsFloatConditionOnStringColumnTest extends DatabaseTestBase {

  /**
   * Tests that a float value matches a string column literally.
   *
   * Strict parameter binding types floats in the mysqli driver, but
   * StatementWrapperIterator::bindArguments() has no case for them, so the PDO
   * drivers still bind them as strings. The same value is therefore compared as
   * a number by one driver and as a string by another.
   *
   * @see \Drupal\Core\Database\StatementWrapperIterator::bindArguments()
   * @see \Drupal\mysqli\Driver\Database\mysqli\Statement::bindStrictParams()
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
    // Matches a single row of the joined table, and switches strict parameter
    // binding on for the whole statement.
    $query->jsonCondition('j.test_field', '$.number', 5, '=');
    $query->condition('t.name', 3.14);
    $query->condition('t.job', 'Float');

    $this->assertEquals([70], $query->execute()->fetchCol());
  }

}
