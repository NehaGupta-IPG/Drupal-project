<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\InvalidQueryException;
use Drupal\Core\Database\Query\JsonCondition;
use Drupal\Core\Database\Query\Merge;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests JSON conditions in the merge query builder.
 *
 * A merge compiles its condition twice: once for the SELECT that looks for an
 * existing row, and once for the UPDATE that follows when that row is found.
 * Every test therefore covers both compilations of the same condition object.
 *
 * @see \Drupal\Core\Database\Query\Merge::execute()
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class MergeJsonTest extends DatabaseTestBase {

  /**
   * The ID the insert part of a merge uses in these tests.
   *
   * The sample data fills the IDs 1 to 10, so a merge that inserts uses the
   * next one. The ID is explicit, so it is the same on every backend.
   */
  protected const INSERT_ID = 11;

  /**
   * Removes the rows a merge inserted and resets the marker column.
   */
  protected function resetJsonTable(): void {
    $this->connection->delete('json')
      ->condition('id', 10, '>')
      ->execute();
    $this->connection->update('json')
      ->fields(['status' => 1])
      ->execute();
  }

  /**
   * Returns the IDs of the rows of the 'json' table with a given status.
   *
   * @param int $status
   *   The status to filter on.
   *
   * @return int[]
   *   The matching row IDs.
   */
  protected function idsWithStatus(int $status): array {
    $ids = $this->connection->select('json', 'j')
      ->fields('j', ['id'])
      ->condition('j.status', $status)
      ->execute()
      ->fetchCol();
    return array_map('intval', $ids);
  }

  /**
   * Returns the number of rows in the 'json' table.
   */
  protected function countRows(): int {
    return (int) $this->connection->select('json', 'j')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Tests JSON conditions with supported operators in a merge query.
   *
   * A condition that matches at least one row makes the merge update every
   * matching row, so the result can be compared against the rows the same
   * condition returns in a SELECT. A condition that matches nothing makes the
   * merge insert instead.
   *
   * @see \Drupal\KernelTests\Core\Database\SelectTest::testJsonCondition()
   */
  public function testJsonConditionMerge(): void {
    foreach (static::providerJsonConditionOperators() as $label => [$jsonpath, $value, $operator, $expected_ids]) {
      $this->resetJsonTable();

      $result = $this->connection->merge('json')
        ->jsonCondition('test_field', $jsonpath, $value, $operator)
        ->updateFields(['status' => 2])
        ->insertFields(['id' => static::INSERT_ID, 'status' => 3])
        ->execute();

      if ($expected_ids === []) {
        $this->assertSame(Merge::STATUS_INSERT, $result, "Wrong status for: $label");
        $this->assertSame([], $this->idsWithStatus(2), "Rows updated for: $label");
        $this->assertSame([static::INSERT_ID], $this->idsWithStatus(3), "No row inserted for: $label");
      }
      else {
        $this->assertSame(Merge::STATUS_UPDATE, $result, "Wrong status for: $label");
        $this->assertEqualsCanonicalizing($expected_ids, $this->idsWithStatus(2), "Wrong rows updated for: $label");
        $this->assertSame(10, $this->countRows(), "Row inserted for: $label");
      }
    }
  }

  /**
   * Tests that a matching JSON condition updates every matching row.
   */
  public function testJsonConditionMergeUpdate(): void {
    // Rows 2 and 3 both hold 10 at $.number.
    $result = $this->connection->merge('json')
      ->jsonCondition('test_field', '$.number', 10, '=')
      ->updateFields(['name' => 'merged'])
      ->insertFields(['id' => static::INSERT_ID])
      ->execute();

    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertSame(10, $this->countRows());

    $names = $this->connection->select('json', 'j')
      ->fields('j', ['id', 'name'])
      ->isNotNull('j.name')
      ->execute()
      ->fetchAllKeyed();
    $this->assertEqualsCanonicalizing([2, 3], array_map('intval', array_keys($names)));
    $this->assertSame(['merged', 'merged'], array_values($names));
  }

  /**
   * Tests that a JSON condition without a match inserts instead.
   *
   * The inserted row satisfies the condition, so a second, identical merge
   * finds it and updates it.
   */
  public function testJsonConditionMergeInsert(): void {
    $result = $this->connection->merge('json')
      ->jsonCondition('test_field', '$.number', 999, '=')
      ->updateFields(['status' => 2])
      ->insertFields([
        'id' => static::INSERT_ID,
        'status' => 3,
        'test_field' => '{"number": 999}',
      ])
      ->execute();

    $this->assertSame(Merge::STATUS_INSERT, $result);
    $this->assertSame(11, $this->countRows());
    $this->assertSame([static::INSERT_ID], $this->idsWithStatus(3));

    $result = $this->connection->merge('json')
      ->jsonCondition('test_field', '$.number', 999, '=')
      ->updateFields(['status' => 2])
      ->insertFields([
        'id' => static::INSERT_ID,
        'status' => 3,
        'test_field' => '{"number": 999}',
      ])
      ->execute();

    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertSame(11, $this->countRows());
    $this->assertSame([static::INSERT_ID], $this->idsWithStatus(2));
  }

  /**
   * Tests that a merge without update fields leaves a matching row untouched.
   */
  public function testJsonConditionMergeWithoutUpdate(): void {
    $result = $this->connection->merge('json')
      ->jsonCondition('test_field', '$.number', 10, '=')
      ->insertFields(['id' => static::INSERT_ID, 'status' => 3])
      ->execute();

    $this->assertNull($result);
    $this->assertSame(10, $this->countRows());
    $this->assertSame([], $this->idsWithStatus(3));
    $this->assertCount(10, $this->idsWithStatus(1));
  }

  /**
   * Tests that JSON conditions combine with keys, AND, OR and conditions.
   */
  public function testJsonConditionMergeCombined(): void {
    // A key narrows the JSON condition to a single row. Rows 2 and 3 both hold
    // 10 at $.number.
    $result = $this->connection->merge('json')
      ->key('id', 3)
      ->jsonCondition('test_field', '$.number', 10, '=')
      ->updateFields(['status' => 2])
      ->execute();
    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertSame([3], $this->idsWithStatus(2));
    $this->assertSame(10, $this->countRows());

    // A key that no row with a matching JSON value holds inserts instead.
    $result = $this->connection->merge('json')
      ->key('id', static::INSERT_ID)
      ->jsonCondition('test_field', '$.number', 10, '=')
      ->updateFields(['status' => 4])
      ->insertFields(['status' => 3])
      ->execute();
    $this->assertSame(Merge::STATUS_INSERT, $result);
    $this->assertSame([static::INSERT_ID], $this->idsWithStatus(3));

    // AND: two JSON conditions, number >= 5 AND boolean = true → row 1 only.
    $this->resetJsonTable();
    $result = $this->connection->merge('json')
      ->jsonCondition('test_field', '$.number', 5, '>=')
      ->jsonCondition('test_field', '$.boolean', TRUE, '=')
      ->updateFields(['status' => 5])
      ->insertFields(['id' => static::INSERT_ID])
      ->execute();
    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertSame([1], $this->idsWithStatus(5));

    // OR: key = "some text" OR number = 5 → rows 1 and 3.
    $this->resetJsonTable();
    $merge = $this->connection->merge('json')
      ->updateFields(['status' => 6])
      ->insertFields(['id' => static::INSERT_ID]);
    $or = $merge->orConditionGroup();
    $or->condition(new JsonCondition('test_field', '$.key', 'some text'));
    $or->condition(new JsonCondition('test_field', '$.number', 5));
    $result = $merge->condition($or)->execute();
    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertEqualsCanonicalizing([1, 3], $this->idsWithStatus(6));
  }

  /**
   * Tests a JSON condition next to an expression field.
   *
   * The UPDATE part of a merge numbers the placeholders of its fields and its
   * expressions separately from the ones the condition generates. A JSON
   * condition that binds values must not overwrite either of them.
   */
  public function testJsonConditionMergeWithExpression(): void {
    // BETWEEN binds two condition placeholders; the key binds a third one.
    $result = $this->connection->merge('json')
      ->key('id', 3)
      ->jsonCondition('test_field', '$.number', [5, 10], 'BETWEEN')
      ->fields(['name' => 'merged', 'score' => 1.5])
      ->expression('status', '[status] + :increment', [':increment' => 5])
      ->execute();

    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertSame(10, $this->countRows());
    $this->assertSame([3], $this->idsWithStatus(6));

    $row = $this->connection->select('json', 'j')
      ->fields('j', ['name', 'score'])
      ->condition('j.id', 3)
      ->execute()
      ->fetchObject();
    $this->assertSame('merged', $row->name);
    $this->assertEquals(1.5, (float) $row->score);
  }

  /**
   * Tests a JSON condition that binds its placeholders before another one.
   *
   * The condition of a merge is compiled twice, against two different query
   * objects, and each compilation restarts the placeholder numbering. A JSON
   * condition that is added before a condition on a regular column has to
   * claim the same placeholder names in both compilations, or the UPDATE part
   * binds the values of one condition to the placeholders of the other.
   */
  public function testJsonConditionMergeWithPlaceholders(): void {
    // CONTAINS binds one LIKE placeholder ahead of the condition on 'id'.
    // Rows 3 and 4 hold a matching string, so the id narrows it to row 3.
    $result = $this->connection->merge('json')
      ->jsonCondition('test_field', '$.key', 'some', 'CONTAINS')
      ->condition('id', 3)
      ->updateFields(['status' => 2])
      ->insertFields(['id' => static::INSERT_ID])
      ->execute();
    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertSame([3], $this->idsWithStatus(2));

    // BETWEEN binds two placeholders ahead of the condition on 'id', which
    // excludes no row: rows 1, 2 and 3 hold 5 or 10 at $.number.
    $this->resetJsonTable();
    $result = $this->connection->merge('json')
      ->jsonCondition('test_field', '$.number', [5, 10], 'BETWEEN')
      ->condition('id', 100, '<')
      ->updateFields(['status' => 5])
      ->insertFields(['id' => static::INSERT_ID])
      ->execute();
    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertEqualsCanonicalizing([1, 2, 3], $this->idsWithStatus(5));

    // Two JSON conditions that both bind placeholders, followed by a condition
    // on a regular column.
    $this->resetJsonTable();
    $result = $this->connection->merge('json')
      ->jsonCondition('test_field', '$.number', [5, 10], 'BETWEEN')
      ->jsonCondition('test_field', '$.key', 'some', 'STARTS_WITH')
      ->condition('id', 100, '<')
      ->updateFields(['status' => 7])
      ->insertFields(['id' => static::INSERT_ID])
      ->execute();
    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertSame([3], $this->idsWithStatus(7));
  }

  /**
   * Tests merging on a wildcard path.
   */
  public function testJsonConditionMergeWildcard(): void {
    $result = $this->connection->merge('json')
      ->jsonCondition('test_field', '$.deps.*.status', 'enabled', '=')
      ->updateFields(['status' => 2])
      ->insertFields(['id' => static::INSERT_ID])
      ->execute();

    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertSame([10], $this->idsWithStatus(2));
    $this->assertSame(10, $this->countRows());
  }

  /**
   * Tests that a merge query with a JSON condition is not string-able.
   */
  public function testJsonConditionMergeToString(): void {
    $merge = $this->connection->merge('json')
      ->jsonCondition('test_field', '$.number', 10, '=');
    $this->expectException(\BadMethodCallException::class);
    $this->expectExceptionMessageIs('The merge query can not be converted to a string');
    (string) $merge;
  }

  /**
   * Tests that a JSON condition satisfies the merge condition requirement.
   */
  public function testJsonConditionMergeCountsAsCondition(): void {
    // A merge without any condition throws, so a merge that only holds a JSON
    // condition proves the condition is registered on the query.
    $result = $this->connection->merge('json')
      ->jsonCondition('test_field', '$.number', 10, '=')
      ->updateFields(['status' => 2])
      ->insertFields(['id' => static::INSERT_ID])
      ->execute();
    $this->assertSame(Merge::STATUS_UPDATE, $result);
  }

  /**
   * Tests that an unsupported operator is rejected when the query is built.
   */
  public function testJsonConditionMergeUnsupportedOperator(): void {
    $this->expectException(InvalidQueryException::class);
    $this->connection->merge('json')
      ->jsonCondition('test_field', '$.number', 10, 'LIKE');
  }

  /**
   * Tests that an invalid jsonpath is rejected when the query is compiled.
   */
  public function testJsonConditionMergePathValidation(): void {
    $merge = $this->connection->merge('json')
      ->jsonCondition('test_field', "$.key' OR 1=1--", 'value')
      ->updateFields(['status' => 2])
      ->insertFields(['id' => static::INSERT_ID]);
    $this->expectException(InvalidQueryException::class);
    $merge->execute();
  }

}
