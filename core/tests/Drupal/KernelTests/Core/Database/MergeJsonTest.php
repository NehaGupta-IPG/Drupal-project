<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\InvalidQueryException;
use Drupal\Core\Database\Query\JsonCastType;
use Drupal\Core\Database\Query\Merge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests JSON conditions and JSON expressions in the merge query builder.
 *
 * A merge compiles its condition twice: once for the SELECT that looks for an
 * existing row, and once for the UPDATE that follows when that row is found.
 * Every condition test therefore covers both compilations of the same
 * condition object. A JSON expression belongs to the UPDATE query part only.
 *
 * @see \Drupal\Core\Database\Query\Merge::execute()
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class MergeJsonTest extends DatabaseTestBase {

  /**
   * The ID the insert part of a merge uses in these tests.
   *
   * Sample rows use IDs 1 through 11. Using the next ID keeps the insert
   * consistent across database backends.
   */
  protected const INSERT_ID = 12;

  /**
   * Removes the rows a merge inserted and resets the marker column.
   */
  protected function resetJsonTable(): void {
    $this->connection->delete('json')
      ->condition('id', 11, '>')
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
   * Returns the value of a column of a single row of the 'json' table.
   *
   * @param int $id
   *   The ID of the row to read.
   * @param string $column
   *   The column to read.
   *
   * @return string|null
   *   The column value.
   */
  protected function fieldValue(int $id, string $column): ?string {
    $value = $this->connection->select('json', 'j')
      ->fields('j', [$column])
      ->condition('j.id', $id)
      ->execute()
      ->fetchField();
    return $value === FALSE ? NULL : $value;
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
  public function testJsonConditionMerge(string $jsonpath, mixed $value, string $operator, array $expected_ids): void {
    $result = $this->connection->merge('json')
      ->jsonCondition('test_field', $jsonpath, $value, $operator)
      ->updateFields(['status' => 2])
      ->insertFields(['id' => static::INSERT_ID, 'status' => 3])
      ->execute();

    if ($expected_ids === []) {
      $this->assertSame(Merge::STATUS_INSERT, $result);
      $this->assertSame([], $this->idsWithStatus(2));
      $this->assertSame([static::INSERT_ID], $this->idsWithStatus(3));
    }
    else {
      $this->assertSame(Merge::STATUS_UPDATE, $result);
      $this->assertEqualsCanonicalizing($expected_ids, $this->idsWithStatus(2));
      $this->assertSame(11, $this->countRows());
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
    $this->assertSame(11, $this->countRows());

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
    $this->assertSame(12, $this->countRows());
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
    $this->assertSame(12, $this->countRows());
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
    $this->assertSame(11, $this->countRows());
    $this->assertSame([], $this->idsWithStatus(3));
    $this->assertCount(11, $this->idsWithStatus(1));
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
    $this->assertSame(11, $this->countRows());

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
    $or->jsonCondition('test_field', '$.key', 'some text');
    $or->jsonCondition('test_field', '$.number', 5);
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
    $this->assertSame(11, $this->countRows());
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
    $this->assertSame(11, $this->countRows());
  }

  /**
   * Tests setting a column to a value extracted from a JSON column.
   *
   * The merge sets 'name' twice, once as a literal and once as a JSON
   * expression. The expression has to win, the same way expression() wins over
   * fields().
   *
   * @see \Drupal\KernelTests\Core\Database\MergeTest::testMergeUpdateExpression()
   */
  public function testJsonExpression(): void {
    $result = $this->connection->merge('json')
      ->key('id', 3)
      ->fields(['name' => 'from fields', 'score' => 9.5])
      ->jsonExpression('name', 'test_field', '$.key')
      ->execute();

    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertSame(11, $this->countRows());
    // The default cast returns text without the quotes of the JSON string.
    $this->assertSame('some text', $this->fieldValue(3, 'name'));
    // The field that the expression does not target is still written.
    $this->assertEquals(9.5, (float) $this->fieldValue(3, 'score'));
  }

  /**
   * Tests that a JSON expression makes the merge run its UPDATE part.
   *
   * A merge that holds no update fields is a no-op on a matching row. A JSON
   * expression alone has to be enough to make it update, because expression()
   * sets the flag that updateFields() sets.
   *
   * @see \Drupal\KernelTests\Core\Database\MergeJsonTest::testJsonConditionMergeWithoutUpdate()
   */
  public function testJsonExpressionWithoutUpdateFields(): void {
    $result = $this->connection->merge('json')
      ->key('id', 3)
      ->jsonExpression('name', 'test_field', '$.key')
      ->execute();

    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertSame('some text', $this->fieldValue(3, 'name'));
  }

  /**
   * Tests that a JSON expression applies to the UPDATE query part only.
   *
   * The first merge inserts, so the expression is not applied. The inserted
   * row satisfies the condition, so the second merge updates it and the
   * expression takes effect.
   */
  public function testJsonExpressionInsert(): void {
    $json = '{"key": "inserted text", "number": 999}';
    for ($i = 0; $i < 2; $i++) {
      $result = $this->connection->merge('json')
        ->jsonCondition('test_field', '$.number', 999, '=')
        ->jsonExpression('name', 'test_field', '$.key')
        ->insertFields([
          'id' => static::INSERT_ID,
          'test_field' => $json,
        ])
        ->execute();

      if ($i === 0) {
        $this->assertSame(Merge::STATUS_INSERT, $result);
        $this->assertNull($this->fieldValue(static::INSERT_ID, 'name'));
      }
      else {
        $this->assertSame(Merge::STATUS_UPDATE, $result);
        $this->assertSame('inserted text', $this->fieldValue(static::INSERT_ID, 'name'));
      }
    }
    $this->assertSame(12, $this->countRows());
  }

  /**
   * Tests the integer, float and text casts of a JSON expression.
   *
   * Each merge is restricted to the rows that hold a value at the path, because
   * an assignment cannot skip the rows holding a value of another type.
   *
   * @see \Drupal\KernelTests\Core\Database\UpdateJsonTest::testJsonExpression()
   */
  public function testJsonExpressionCasts(): void {
    // A text cast assigns the string without its quotes: rows 3 and 4.
    $result = $this->connection->merge('json')
      ->jsonCondition('test_field', '$.key', NULL, 'HAS KEY')
      ->jsonExpression('name', 'test_field', '$.key')
      ->insertFields(['id' => static::INSERT_ID])
      ->execute();
    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertSame(11, $this->countRows());
    $this->assertSame('some text', $this->fieldValue(3, 'name'));
    $this->assertSame('some other text', $this->fieldValue(4, 'name'));

    // An integer cast assigns a number: rows 1, 2 and 3.
    $result = $this->connection->merge('json')
      ->jsonCondition('test_field', '$.number', NULL, 'HAS KEY')
      ->jsonExpression('status', 'test_field', '$.number', JsonCastType::Int)
      ->insertFields(['id' => static::INSERT_ID])
      ->execute();
    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertSame([1], $this->idsWithStatus(5));
    $this->assertEqualsCanonicalizing([2, 3], $this->idsWithStatus(10));

    // A float cast assigns a fractional number: rows 5 and 8.
    $result = $this->connection->merge('json')
      ->jsonCondition('test_field', '$.float', NULL, 'HAS KEY')
      ->jsonExpression('score', 'test_field', '$.float', JsonCastType::Float)
      ->insertFields(['id' => static::INSERT_ID])
      ->execute();
    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertEquals(1.5, (float) $this->fieldValue(5, 'score'));
    $this->assertEquals(3.14, (float) $this->fieldValue(8, 'score'));
  }

  /**
   * Tests that a boolean cast assigns 1 or 0 on every backend.
   */
  public function testJsonExpressionBooleanCast(): void {
    // Start from a status that cannot be confused with a cast boolean.
    $this->connection->update('json')
      ->fields(['status' => 9])
      ->execute();

    $result = $this->connection->merge('json')
      ->jsonCondition('test_field', '$.boolean', NULL, 'HAS KEY')
      ->jsonExpression('status', 'test_field', '$.boolean', JsonCastType::Bool)
      ->insertFields(['id' => static::INSERT_ID])
      ->execute();

    $this->assertSame(Merge::STATUS_UPDATE, $result);
    // Rows 1 and 4 hold true, row 2 holds false, the rest are untouched.
    $this->assertEqualsCanonicalizing([1, 4], $this->idsWithStatus(1));
    $this->assertSame([2], $this->idsWithStatus(0));
    $this->assertEqualsCanonicalizing([3, 5, 6, 7, 8, 9, 10, 11], $this->idsWithStatus(9));
  }

  /**
   * Tests two JSON expressions and a nested path in one merge.
   */
  public function testJsonExpressionMultiple(): void {
    $result = $this->connection->merge('json')
      ->key('id', 8)
      ->jsonExpression('name', 'test_field', '$.nested.inner')
      ->jsonExpression('score', 'test_field', '$.float', JsonCastType::Float)
      ->execute();

    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertSame('deep value', $this->fieldValue(8, 'name'));
    $this->assertEquals(3.14, (float) $this->fieldValue(8, 'score'));
  }

  /**
   * Tests a JSON expression next to an expression that binds arguments.
   *
   * A JSON expression binds no arguments of its own, so the named placeholder
   * of the other expression has to survive both query parts.
   */
  public function testJsonExpressionWithExpression(): void {
    $result = $this->connection->merge('json')
      ->key('id', 3)
      ->jsonExpression('name', 'test_field', '$.key')
      ->expression('status', '[status] + :increment', [':increment' => 5])
      ->execute();

    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertSame('some text', $this->fieldValue(3, 'name'));
    $this->assertSame([3], $this->idsWithStatus(6));
  }

  /**
   * Tests a JSON expression restricted by a JSON condition.
   */
  public function testJsonExpressionWithJsonCondition(): void {
    // Rows 3 and 4 hold a string at $.key, row 4 is the only one that matches.
    $result = $this->connection->merge('json')
      ->jsonCondition('test_field', '$.key', 'other', 'CONTAINS')
      ->jsonExpression('name', 'test_field', '$.key')
      ->insertFields(['id' => static::INSERT_ID])
      ->execute();

    $this->assertSame(Merge::STATUS_UPDATE, $result);
    $this->assertSame(11, $this->countRows());
    $this->assertSame('some other text', $this->fieldValue(4, 'name'));
    $this->assertNull($this->fieldValue(3, 'name'));
  }

  /**
   * Tests that a JSON expression assigns NULL when the path does not exist.
   */
  public function testJsonExpressionMissingPath(): void {
    $this->connection->update('json')
      ->fields(['name' => 'placeholder'])
      ->execute();

    $result = $this->connection->merge('json')
      ->jsonCondition('test_field', '$.number', NULL, 'HAS KEY')
      ->jsonExpression('name', 'test_field', '$.nonexistent')
      ->insertFields(['id' => static::INSERT_ID])
      ->execute();

    $this->assertSame(Merge::STATUS_UPDATE, $result);
    // Rows 1, 2 and 3 hold a number, so only they lose their name.
    $named = $this->connection->select('json', 'j')
      ->fields('j', ['id'])
      ->isNotNull('j.name')
      ->execute()
      ->fetchCol();
    $this->assertEqualsCanonicalizing([4, 5, 6, 7, 8, 9, 10, 11], array_map('intval', $named));
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
   * Tests that a JSON expression rejects an invalid jsonpath.
   */
  public function testJsonExpressionPathValidation(): void {
    $this->expectException(InvalidQueryException::class);
    $this->connection->merge('json')
      ->jsonExpression('name', 'test_field', "$.key' OR 1=1--");
  }

  /**
   * Tests that a JSON expression rejects a wildcard path.
   *
   * A wildcard matches any number of values, and an assignment takes one.
   * testJsonConditionMergeWildcard() covers the condition, which does expand
   * a wildcard.
   */
  public function testJsonExpressionWildcard(): void {
    $this->expectException(InvalidQueryException::class);
    $this->expectExceptionMessage('contains a wildcard, which is not supported when casting a value');
    $this->connection->merge('json')
      ->jsonExpression('name', 'test_field', '$.deps.*.status');
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
