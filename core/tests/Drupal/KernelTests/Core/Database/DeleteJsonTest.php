<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\InvalidQueryException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests JSON conditions in the delete query builder.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class DeleteJsonTest extends DatabaseTestBase {

  /**
   * The IDs of all rows the sample data inserts into the 'json' table.
   */
  protected const ALL_IDS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];

  /**
   * Empties the 'json' table and inserts the sample data again.
   *
   * A DELETE removes the rows it matches, so a test case that runs more than
   * one delete has to restore the table in between.
   */
  protected function resetJsonTable(): void {
    $this->connection->delete('json')->execute();
    $this->addSampleJsonData();
  }

  /**
   * Returns the IDs left in the 'json' table.
   *
   * @return int[]
   *   The remaining row IDs.
   */
  protected function remainingIds(): array {
    $ids = $this->connection->select('json', 'j')
      ->fields('j', ['id'])
      ->execute()
      ->fetchCol();
    return array_map('intval', $ids);
  }

  /**
   * Tests JSON conditions with supported operators in a delete query.
   *
   * Every case starts from the full sample data, so the rows a DELETE removes
   * can be compared against the rows the same condition returns in a SELECT.
   *
   * @param string $jsonpath
   *   The jsonpath to compare.
   * @param mixed $value
   *   The value to compare against.
   * @param string $operator
   *   The comparison operator.
   * @param array $expected_ids
   *   The IDs of the sample rows the condition matches.
   *
   * @see \Drupal\KernelTests\Core\Database\SelectTest::testJsonCondition()
   */
  #[DataProvider('providerJsonConditionOperators')]
  public function testJsonConditionDelete(string $jsonpath, mixed $value, string $operator, array $expected_ids): void {
    $num_deleted = $this->connection->delete('json')
      ->jsonCondition('test_field', $jsonpath, $value, $operator)
      ->execute();

    $this->assertSame(count($expected_ids), $num_deleted);
    $this->assertEqualsCanonicalizing(
      array_values(array_diff(static::ALL_IDS, $expected_ids)),
      $this->remainingIds(),
    );
  }

  /**
   * Tests that JSON conditions combine with AND, OR and regular conditions.
   */
  public function testJsonConditionDeleteCombined(): void {
    // AND: number >= 5 AND boolean = true → row 1 only.
    $num_deleted = $this->connection->delete('json')
      ->jsonCondition('test_field', '$.number', 5, '>=')
      ->jsonCondition('test_field', '$.boolean', TRUE, '=')
      ->execute();
    $this->assertSame(1, $num_deleted);
    $this->assertEqualsCanonicalizing([2, 3, 4, 5, 6, 7, 8, 9, 10, 11], $this->remainingIds());

    // OR: key = "some text" OR number = 5 → rows 1 and 3, of which row 1 is
    // already gone.
    $this->resetJsonTable();
    $delete = $this->connection->delete('json');
    $or = $delete->orConditionGroup();
    $or->jsonCondition('test_field', '$.key', 'some text');
    $or->jsonCondition('test_field', '$.number', 5);
    $num_deleted = $delete->condition($or)->execute();
    $this->assertSame(2, $num_deleted);
    $this->assertEqualsCanonicalizing([2, 4, 5, 6, 7, 8, 9, 10, 11], $this->remainingIds());

    // Mixed: a JSON condition AND a condition on a regular column. Rows 2 and
    // 3 both hold 10 at $.number, so the id narrows the delete to row 3.
    $this->resetJsonTable();
    $num_deleted = $this->connection->delete('json')
      ->jsonCondition('test_field', '$.number', 10, '=')
      ->condition('id', 3)
      ->execute();
    $this->assertSame(1, $num_deleted);
    $this->assertEqualsCanonicalizing([1, 2, 4, 5, 6, 7, 8, 9, 10, 11], $this->remainingIds());
  }

  /**
   * Tests that JSON condition placeholders do not collide with other ones.
   *
   * Operators such as BETWEEN and CONTAINS bind their own placeholders. Those
   * must not overwrite the placeholders of a condition on a regular column in
   * the same query.
   */
  public function testJsonConditionDeleteWithPlaceholders(): void {
    // BETWEEN binds two placeholders and matches rows 1, 2 and 3. The regular
    // condition binds a third one and excludes row 1.
    $num_deleted = $this->connection->delete('json')
      ->jsonCondition('test_field', '$.number', [5, 10], 'BETWEEN')
      ->condition('id', 1, '<>')
      ->execute();
    $this->assertSame(2, $num_deleted);
    $this->assertEqualsCanonicalizing([1, 4, 5, 6, 7, 8, 9, 10, 11], $this->remainingIds());

    // CONTAINS binds a LIKE placeholder next to two IN placeholders.
    $this->resetJsonTable();
    $num_deleted = $this->connection->delete('json')
      ->jsonCondition('test_field', '$.key', 'some', 'CONTAINS')
      ->condition('id', [3, 9], 'IN')
      ->execute();
    $this->assertSame(1, $num_deleted);
    $this->assertEqualsCanonicalizing([1, 2, 4, 5, 6, 7, 8, 9, 10, 11], $this->remainingIds());

    // Two JSON conditions that both bind placeholders.
    $this->resetJsonTable();
    $num_deleted = $this->connection->delete('json')
      ->jsonCondition('test_field', '$.number', [5, 10], 'BETWEEN')
      ->jsonCondition('test_field', '$.key', 'some', 'STARTS_WITH')
      ->execute();
    $this->assertSame(1, $num_deleted);
    $this->assertEqualsCanonicalizing([1, 2, 4, 5, 6, 7, 8, 9, 10, 11], $this->remainingIds());
  }

  /**
   * Tests deleting rows on a wildcard path.
   */
  public function testJsonConditionDeleteWildcard(): void {
    $num_deleted = $this->connection->delete('json')
      ->jsonCondition('test_field', '$.deps.*.status', 'enabled', '=')
      ->execute();
    $this->assertSame(1, $num_deleted);
    $this->assertEqualsCanonicalizing([1, 2, 3, 4, 5, 6, 7, 8, 9, 11], $this->remainingIds());
  }

  /**
   * Tests that a delete without matching rows leaves the table untouched.
   */
  public function testJsonConditionDeleteNoMatch(): void {
    $num_deleted = $this->connection->delete('json')
      ->jsonCondition('test_field', '$.nonexistent', 'value', '=')
      ->execute();
    $this->assertSame(0, $num_deleted);
    $this->assertEqualsCanonicalizing(static::ALL_IDS, $this->remainingIds());
  }

  /**
   * Tests that a delete query with a JSON condition compiles to a string.
   */
  public function testJsonConditionDeleteToString(): void {
    $delete = $this->connection->delete('json')
      ->jsonCondition('test_field', '$.number', 10, '=');
    $this->assertStringContainsString('DELETE FROM', (string) $delete);
    $this->assertStringContainsString('WHERE', (string) $delete);
  }

  /**
   * Tests that an unsupported operator is rejected when the query is built.
   */
  public function testJsonConditionDeleteUnsupportedOperator(): void {
    $this->expectException(InvalidQueryException::class);
    $this->connection->delete('json')
      ->jsonCondition('test_field', '$.number', 10, 'LIKE');
  }

  /**
   * Tests that an invalid jsonpath is rejected when the query is compiled.
   */
  public function testJsonConditionDeletePathValidation(): void {
    $delete = $this->connection->delete('json')
      ->jsonCondition('test_field', "$.key' OR 1=1--", 'value');
    $this->expectException(InvalidQueryException::class);
    $delete->execute();
  }

}
