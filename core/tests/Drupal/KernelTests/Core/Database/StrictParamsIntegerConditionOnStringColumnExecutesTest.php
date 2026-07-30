<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that an integer value can be compared to a string column at all.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class StrictParamsIntegerConditionOnStringColumnExecutesTest extends DatabaseTestBase {

  /**
   * Tests that comparing an integer value to a string column is supported.
   *
   * No row matches, so every backend returns an empty result. PostgreSQL
   * instead fails with SQLSTATE 42883, because strict parameter binding sends
   * the value as an integer and there is no character varying = integer
   * operator.
   */
  public function testIntegerConditionOnStringColumnExecutes(): void {
    $query = $this->connection->select('test', 't');
    $query->addField('t', 'name');
    $query->join('json', 'j', '1 = 1');
    // Matches a single row of the joined table, and switches strict parameter
    // binding on for the whole statement.
    $query->jsonCondition('j.test_field', '$.number', 5, '=');
    $query->condition('t.name', 26);

    $this->assertSame([], $query->execute()->fetchCol());
  }

}
