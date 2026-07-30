<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests an integer condition value against a string column.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class StrictParamsIntegerConditionOnStringColumnTest extends DatabaseTestBase {

  /**
   * Tests that an integer value matches a string column literally.
   *
   * Adding a JsonCondition must not change how conditions on other columns
   * match. MySQL and MariaDB compare a string column to an integer value
   * numerically, so ' 10' and '10.0' also match when the value is bound as an
   * integer.
   */
  public function testIntegerConditionOnStringColumn(): void {
    $this->connection->insert('test')
      ->fields(['name', 'age', 'job'])
      ->values(['name' => '10', 'age' => 60, 'job' => 'Strict'])
      ->values(['name' => ' 10', 'age' => 61, 'job' => 'Strict'])
      ->values(['name' => '10.0', 'age' => 62, 'job' => 'Strict'])
      ->execute();

    $query = $this->connection->select('test', 't');
    $query->addField('t', 'age');
    $query->join('json', 'j', '1 = 1');
    // Matches a single row of the joined table, and switches strict parameter
    // binding on.
    $query->jsonCondition('j.test_field', '$.number', 5, '=');
    $query->condition('t.name', 10);
    $query->condition('t.job', 'Strict');

    $this->assertEquals([60], $query->execute()->fetchCol());
  }

}
