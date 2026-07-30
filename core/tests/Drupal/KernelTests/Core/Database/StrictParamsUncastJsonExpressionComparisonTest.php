<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests an integer compared to a JSON value extracted without a cast.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class StrictParamsUncastJsonExpressionComparisonTest extends DatabaseTestBase {

  /**
   * Tests that an uncast JSON extraction can be compared to an integer.
   *
   * JsonCondition always casts the extracted value, but JsonExpression is also
   * documented for direct use. On PostgreSQL the extraction returns text, which
   * cannot be compared to an integer bound under strict parameter binding.
   */
  public function testUncastJsonExpressionComparison(): void {
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    // Switches strict parameter binding on, and skips the row with a NULL
    // column.
    $query->jsonCondition('j.test_field', '$.number', NULL, 'IS NOT NULL');
    $expression = $this->connection->jsonExpression('j.test_field', '$.number');
    $query->where($expression->toSql() . ' = :value', [':value' => 10]);

    $this->assertEqualsCanonicalizing([2, 3], $query->execute()->fetchCol());
  }

}
