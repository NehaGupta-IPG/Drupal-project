<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\Core\Database\Query\JsonCondition;
use Drupal\KernelTests\Core\Database\DriverSpecificDatabaseTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the string operators of a JSON condition on PostgreSQL.
 */
#[CoversClass(JsonCondition::class)]
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class JsonConditionTest extends DriverSpecificDatabaseTestBase {

  /**
   * Data provider of the string operators and a value in another case.
   *
   * The values match rows 3 and 4 of the 'json' table, which hold "some text"
   * and "some other text" at $.key.
   *
   * @return array[]
   *   Test cases, each an array of an operator, a value and the IDs of the
   *   sample rows the condition matches.
   *
   * @see \Drupal\KernelTests\Core\Database\DatabaseTestSchemaDataTrait::addSampleJsonData()
   */
  public static function providerStringOperators(): array {
    return [
      'CONTAINS' => ['CONTAINS', 'SOME TEXT', [3]],
      'STARTS_WITH' => ['STARTS_WITH', 'SOME', [3, 4]],
      'ENDS_WITH' => ['ENDS_WITH', 'TEXT', [3, 4]],
    ];
  }

  /**
   * Tests that the string operators map to ILIKE.
   *
   * PostgreSQL's LIKE is case-sensitive. The driver maps LIKE to ILIKE, so
   * that a condition is case-insensitive on every driver. JsonCondition takes
   * that mapping for its string operators.
   *
   * @param string $operator
   *   The comparison operator.
   * @param string $value
   *   The value to compare against, in another case than the stored value.
   * @param array $expected_ids
   *   The IDs of the sample rows the condition matches.
   *
   * @see \Drupal\pgsql\Driver\Database\pgsql\Connection::mapConditionOperator()
   */
  #[DataProvider('providerStringOperators')]
  public function testStringOperatorIsCaseInsensitive(string $operator, string $value, array $expected_ids): void {
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonCondition('test_field', '$.key', $value, $operator);

    $this->assertStringContainsString(' ILIKE ', (string) $query);
    $this->assertEqualsCanonicalizing($expected_ids, array_map('intval', $query->execute()->fetchCol()));
  }

}
