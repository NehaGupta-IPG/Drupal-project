<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\InvalidQueryException;
use Drupal\Core\Database\Query\JsonCastType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests JSON conditions and JSON expressions in the update query builder.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class UpdateJsonTest extends DatabaseTestBase {

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
   * Tests JSON conditions with supported operators in an update query.
   *
   * Every case marks the matching rows with a new status, so the rows an
   * UPDATE affects can be compared against the rows the same condition
   * returns in a SELECT.
   *
   * @see \Drupal\KernelTests\Core\Database\SelectTest::testJsonCondition()
   */
  public function testJsonConditionUpdate(): void {
    foreach (static::providerJsonConditionOperators() as $label => [$jsonpath, $value, $operator, $expected_ids]) {
      // Reset the marker column, so each case starts from the same state.
      $this->connection->update('json')
        ->fields(['status' => 1])
        ->execute();

      $num_updated = $this->connection->update('json')
        ->fields(['status' => 2])
        ->jsonCondition('test_field', $jsonpath, $value, $operator)
        ->execute();

      $this->assertSame(count($expected_ids), $num_updated, "Wrong row count for: $label");
      $this->assertEqualsCanonicalizing($expected_ids, $this->idsWithStatus(2), "Wrong rows updated for: $label");
    }
  }

  /**
   * Tests that JSON conditions combine with AND, OR and regular conditions.
   */
  public function testJsonConditionUpdateCombined(): void {
    // AND: number >= 5 AND boolean = true → row 1 only.
    $num_updated = $this->connection->update('json')
      ->fields(['status' => 2])
      ->jsonCondition('test_field', '$.number', 5, '>=')
      ->jsonCondition('test_field', '$.boolean', TRUE, '=')
      ->execute();
    $this->assertSame(1, $num_updated);
    $this->assertEquals([1], $this->idsWithStatus(2));

    // OR: key = "some text" OR number = 5 → rows 1, 3.
    $update = $this->connection->update('json')->fields(['status' => 3]);
    $or = $update->orConditionGroup();
    $or->jsonCondition('test_field', '$.key', 'some text');
    $or->jsonCondition('test_field', '$.number', 5);
    $num_updated = $update->condition($or)->execute();
    $this->assertSame(2, $num_updated);
    $this->assertEqualsCanonicalizing([1, 3], $this->idsWithStatus(3));

    // Mixed: a JSON condition AND a condition on a regular column.
    $num_updated = $this->connection->update('json')
      ->fields(['status' => 4])
      ->jsonCondition('test_field', '$.number', 10, '=')
      ->condition('id', 3)
      ->execute();
    $this->assertSame(1, $num_updated);
    $this->assertEquals([3], $this->idsWithStatus(4));
  }

  /**
   * Tests that JSON condition placeholders do not collide with other ones.
   *
   * An update query numbers the placeholders of fields() and expression()
   * separately from the ones a condition generates. A JSON condition that
   * binds values must not overwrite either of them.
   */
  public function testJsonConditionUpdateWithPlaceholders(): void {
    // BETWEEN binds two condition placeholders; matches rows 1, 2 and 3.
    $num_updated = $this->connection->update('json')
      ->fields(['name' => 'matched', 'score' => 1.5])
      ->expression('status', '[status] + :increment', [':increment' => 5])
      ->jsonCondition('test_field', '$.number', [5, 10], 'BETWEEN')
      ->execute();
    $this->assertSame(3, $num_updated);

    $rows = $this->connection->select('json', 'j')
      ->fields('j', ['id', 'status', 'name', 'score'])
      ->condition('j.name', 'matched')
      ->execute()
      ->fetchAllAssoc('id');
    $this->assertEqualsCanonicalizing([1, 2, 3], array_map('intval', array_keys($rows)));
    foreach ($rows as $row) {
      $this->assertSame(6, (int) $row->status);
      $this->assertEquals(1.5, (float) $row->score);
    }

    // CONTAINS binds a single LIKE placeholder; matches rows 3 and 4.
    $num_updated = $this->connection->update('json')
      ->fields(['name' => 'liked'])
      ->jsonCondition('test_field', '$.key', 'some', 'CONTAINS')
      ->execute();
    $this->assertSame(2, $num_updated);
  }

  /**
   * Tests updating the JSON column itself, then matching the new value.
   */
  public function testUpdateJsonColumn(): void {
    $num_updated = $this->connection->update('json')
      ->fields(['test_field' => '{"number": 42, "key": "rewritten"}'])
      ->condition('id', 1)
      ->execute();
    $this->assertSame(1, $num_updated);

    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonCondition('test_field', '$.number', 42, '=');
    $this->assertEquals([1], $query->execute()->fetchCol());

    // Setting the column to NULL removes it from every path match.
    $this->connection->update('json')
      ->fields(['test_field' => NULL])
      ->condition('id', 1)
      ->execute();

    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonCondition('test_field', '$.number', NULL, 'HAS KEY');
    $this->assertEqualsCanonicalizing([2, 3], array_map('intval', $query->execute()->fetchCol()));
  }

  /**
   * Tests setting a column to a value extracted from a JSON column.
   */
  public function testJsonExpression(): void {
    // The default cast returns text without the quotes of the JSON string.
    $update = $this->connection->update('json')
      ->jsonExpression('name', 'test_field', '$.key')
      ->jsonCondition('test_field', '$.key', NULL, 'HAS KEY');
    $this->assertSame(2, $update->execute());

    $names = $this->connection->select('json', 'j')
      ->fields('j', ['id', 'name'])
      ->isNotNull('j.name')
      ->execute()
      ->fetchAllKeyed();
    $this->assertEquals([3 => 'some text', 4 => 'some other text'], $names);

    // An integer cast assigns a number.
    $num_updated = $this->connection->update('json')
      ->jsonExpression('status', 'test_field', '$.number', JsonCastType::Int)
      ->jsonCondition('test_field', '$.number', NULL, 'HAS KEY')
      ->execute();
    $this->assertSame(3, $num_updated);
    $this->assertEquals([1], $this->idsWithStatus(5));
    $this->assertEqualsCanonicalizing([2, 3], $this->idsWithStatus(10));

    // A float cast assigns a fractional number.
    $num_updated = $this->connection->update('json')
      ->jsonExpression('score', 'test_field', '$.float', JsonCastType::Float)
      ->jsonCondition('test_field', '$.float', NULL, 'HAS KEY')
      ->execute();
    $this->assertSame(2, $num_updated);
    $scores = $this->connection->select('json', 'j')
      ->fields('j', ['id', 'score'])
      ->isNotNull('j.score')
      ->execute()
      ->fetchAllKeyed();
    $this->assertEquals(1.5, (float) $scores[5]);
    $this->assertEquals(3.14, (float) $scores[8]);
  }

  /**
   * Tests that a boolean cast assigns 1 or 0 on every backend.
   */
  public function testJsonExpressionBooleanCast(): void {
    // Start from a status that cannot be confused with a cast boolean.
    $this->connection->update('json')
      ->fields(['status' => 9])
      ->execute();

    $num_updated = $this->connection->update('json')
      ->jsonExpression('status', 'test_field', '$.boolean', JsonCastType::Bool)
      ->jsonCondition('test_field', '$.boolean', NULL, 'HAS KEY')
      ->execute();
    $this->assertSame(3, $num_updated);

    // Rows 1 and 4 hold true, row 2 holds false, the rest are untouched.
    $this->assertEqualsCanonicalizing([1, 4], $this->idsWithStatus(1));
    $this->assertEquals([2], $this->idsWithStatus(0));
    $this->assertEqualsCanonicalizing([3, 5, 6, 7, 8, 9, 10], $this->idsWithStatus(9));
  }

  /**
   * Tests JSON expressions on nested, hyphenated and indexed paths.
   */
  public function testJsonExpressionPaths(): void {
    $cases = [
      'nested' => ['$.nested.inner', 8, 'deep value'],
      'hyphenated' => ['$.hyphen-key', 9, 'hyphen value'],
      'bracket notation' => ['$["hyphen-key"]', 9, 'hyphen value'],
      'array index' => ['$.items[1]', 8, '20'],
    ];
    foreach ($cases as $label => [$jsonpath, $id, $expected]) {
      $this->connection->update('json')
        ->jsonExpression('name', 'test_field', $jsonpath)
        ->condition('id', $id)
        ->execute();

      $name = $this->connection->select('json', 'j')
        ->fields('j', ['name'])
        ->condition('j.id', $id)
        ->execute()
        ->fetchField();
      $this->assertSame($expected, $name, "Wrong value for: $label");
    }
  }

  /**
   * Tests that a JSON expression takes precedence over fields().
   */
  public function testJsonExpressionPrecedence(): void {
    $num_updated = $this->connection->update('json')
      ->fields(['name' => 'from fields', 'score' => 9.5])
      ->jsonExpression('name', 'test_field', '$.key')
      ->condition('id', 3)
      ->execute();
    $this->assertSame(1, $num_updated);

    $row = $this->connection->select('json', 'j')
      ->fields('j', ['name', 'score'])
      ->condition('j.id', 3)
      ->execute()
      ->fetchObject();
    // The JSON expression wins for 'name', while 'score' is still written.
    $this->assertSame('some text', $row->name);
    $this->assertEquals(9.5, (float) $row->score);
  }

  /**
   * Tests a JSON expression next to an expression that binds arguments.
   *
   * An update query numbers the placeholders of fields(), of expression()
   * arguments and of the condition separately. A JSON expression binds no
   * arguments of its own, so all three have to survive next to it.
   *
   * @see \Drupal\KernelTests\Core\Database\UpdateComplexTest::testUpdateExpression()
   */
  public function testJsonExpressionWithExpression(): void {
    $num_updated = $this->connection->update('json')
      ->fields(['score' => 9.5])
      ->jsonExpression('name', 'test_field', '$.key')
      ->expression('status', '[status] + :increment', [':increment' => 5])
      ->condition('id', 3)
      ->execute();
    $this->assertSame(1, $num_updated);

    $row = $this->connection->select('json', 'j')
      ->fields('j', ['name', 'score', 'status'])
      ->condition('j.id', 3)
      ->execute()
      ->fetchObject();
    $this->assertSame('some text', $row->name);
    $this->assertEquals(9.5, (float) $row->score);
    $this->assertSame(6, (int) $row->status);
  }

  /**
   * Tests several JSON expressions in one update query.
   */
  public function testJsonExpressionMultiple(): void {
    // Row 8 holds a nested string, a float and an array of numbers.
    $num_updated = $this->connection->update('json')
      ->jsonExpression('name', 'test_field', '$.nested.inner')
      ->jsonExpression('score', 'test_field', '$.float', JsonCastType::Float)
      ->jsonExpression('status', 'test_field', '$.items[1]', JsonCastType::Int)
      ->condition('id', 8)
      ->execute();
    $this->assertSame(1, $num_updated);

    $row = $this->connection->select('json', 'j')
      ->fields('j', ['name', 'score', 'status'])
      ->condition('j.id', 8)
      ->execute()
      ->fetchObject();
    $this->assertSame('deep value', $row->name);
    $this->assertEquals(3.14, (float) $row->score);
    $this->assertSame(20, (int) $row->status);
  }

  /**
   * Tests that a second JSON expression for the same column wins.
   *
   * Expression fields are keyed by column, the same way fields() is, so the
   * later call replaces the earlier one.
   */
  public function testJsonExpressionOverride(): void {
    // Row 9 holds a string at $.hyphen-key and a number below $.nested-obj.
    $num_updated = $this->connection->update('json')
      ->jsonExpression('name', 'test_field', '$.hyphen-key')
      ->jsonExpression('name', 'test_field', '$.nested-obj.inner-key')
      ->condition('id', 9)
      ->execute();
    $this->assertSame(1, $num_updated);

    $name = $this->connection->select('json', 'j')
      ->fields('j', ['name'])
      ->condition('j.id', 9)
      ->execute()
      ->fetchField();
    $this->assertSame('42', $name);
  }

  /**
   * Tests an expression whose subquery extracts a JSON value.
   *
   * This is the JSON counterpart of an expression that takes a subselect. The
   * subquery compiles against the update query, so its placeholders have to be
   * numbered next to the ones of the condition of the update.
   *
   * @see \Drupal\KernelTests\Core\Database\UpdateComplexTest::testSubSelectUpdate()
   */
  public function testJsonExpressionSubSelect(): void {
    $subselect = $this->connection->select('json', 'j');
    $subselect->addJsonExpression('test_field', '$.float', 'float_value', JsonCastType::Float);
    $subselect->condition('j.id', 8);
    // Clone this to make sure we are running a different query when asserting.
    $select = clone $subselect;

    $num_updated = $this->connection->update('json')
      ->expression('score', $subselect)
      ->condition('id', 1)
      ->execute();
    $this->assertSame(1, $num_updated);

    $score = $this->connection->select('json', 'j')
      ->fields('j', ['score'])
      ->condition('j.id', 1)
      ->execute()
      ->fetchField();
    $this->assertEquals(3.14, (float) $score);
    $this->assertEquals((float) $select->execute()->fetchField(), (float) $score);
  }

  /**
   * Tests that a JSON expression assigns NULL when the path does not exist.
   */
  public function testJsonExpressionMissingPath(): void {
    $this->connection->update('json')
      ->fields(['name' => 'placeholder'])
      ->execute();

    $num_updated = $this->connection->update('json')
      ->jsonExpression('name', 'test_field', '$.nonexistent')
      ->execute();
    $this->assertSame(10, $num_updated);

    $names = $this->connection->select('json', 'j')
      ->fields('j', ['name'])
      ->isNotNull('j.name')
      ->execute()
      ->fetchCol();
    $this->assertSame([], $names);
  }

  /**
   * Tests that a JSON expression rejects an invalid jsonpath.
   */
  public function testJsonExpressionPathValidation(): void {
    $this->expectException(InvalidQueryException::class);
    $this->connection->update('json')
      ->jsonExpression('name', 'test_field', "$.key' OR 1=1--");
  }

}
