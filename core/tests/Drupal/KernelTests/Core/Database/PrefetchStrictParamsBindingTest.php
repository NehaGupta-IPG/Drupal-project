<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that strict parameter binding is applied by all statement classes.
 *
 * Drivers that prefetch their results use StatementPrefetchIterator instead of
 * StatementWrapperIterator. Both bind arguments with their native PHP type when
 * a query needs strict typing, for example a query with a JSON condition.
 *
 * @see \Drupal\Core\Database\StatementPrefetchIterator::bindArguments()
 * @see \Drupal\Core\Database\StatementWrapperIterator::bindArguments()
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class PrefetchStrictParamsBindingTest extends DatabaseTestBase {

  /**
   * Tests that a FALSE condition value matches an integer column value of 0.
   *
   * A JsonCondition switches strict parameter binding on, so the FALSE value is
   * bound as a boolean. Without strict binding it is bound as a string, which
   * does not match on drivers that do not coerce an empty string to 0.
   */
  public function testBooleanConditionOnIntegerColumn(): void {
    $this->connection->insert('json')
      ->fields(['id', 'status', 'test_field'])
      ->values(['id' => 11, 'status' => 0, 'test_field' => '{"number": 99}'])
      ->execute();

    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonCondition('j.test_field', '$.number', 99, '=');
    $query->condition('status', FALSE);

    $this->assertEquals([11], $query->execute()->fetchCol());
  }

}
