<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\InvalidQueryException;
use Drupal\Core\Database\Query\JsonCastType;
use Drupal\Core\Database\Query\SelectInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests JSON conditions and JSON expressions in the insert query builder.
 *
 * An INSERT statement has no clauses of its own to hold them, so both apply to
 * the select query the inserted rows come from. Every test inserts into the
 * 'json' table itself. The sample data uses the IDs 1 to 10, so the rows an
 * insert adds are the rows with a higher ID.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class InsertJsonTest extends DatabaseTestBase {

  /**
   * The highest ID of the sample data.
   */
  protected const LAST_SAMPLE_ID = 10;

  /**
   * Returns a select query on the sample rows of the 'json' table.
   *
   * The select is limited to the sample rows, so a row an insert adds can
   * never be selected again by a later insert.
   *
   * @param string $marker
   *   The column the ID of the source row is written to. This tells which rows
   *   the insert selected.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The select query.
   */
  protected function sampleSelect(string $marker = 'status'): SelectInterface {
    $select = $this->connection->select('json', 'j');
    $select->addField('j', 'id', $marker);
    $select->condition('j.id', static::LAST_SAMPLE_ID, '<=');
    return $select;
  }

  /**
   * Returns the rows an insert added, keyed by the ID of their source row.
   *
   * @param string $column
   *   The column holding the value the insert wrote.
   * @param string $marker
   *   The column holding the ID of the source row.
   *
   * @return array
   *   The written values, keyed by source row ID.
   */
  protected function insertedRows(string $column = 'status', string $marker = 'status'): array {
    $rows = $this->connection->select('json', 'j')
      ->fields('j', array_unique([$marker, $column]))
      ->condition('j.id', static::LAST_SAMPLE_ID, '>')
      ->execute()
      ->fetchAll();

    $values = [];
    foreach ($rows as $row) {
      $values[(int) $row->{$marker}] = $row->{$column};
    }
    return $values;
  }

  /**
   * Removes the rows added by an earlier insert.
   */
  protected function resetInsertedRows(): void {
    $this->connection->delete('json')
      ->condition('id', static::LAST_SAMPLE_ID, '>')
      ->execute();
  }

  /**
   * Tests JSON conditions with supported operators in an insert query.
   *
   * Every case copies the ID of the matching rows into the marker column, so
   * the rows an INSERT selects can be compared against the rows the same
   * condition returns in a SELECT.
   *
   * @see \Drupal\KernelTests\Core\Database\SelectTest::testJsonCondition()
   */
  public function testJsonConditionInsert(): void {
    foreach (static::providerJsonConditionOperators() as $label => [$jsonpath, $value, $operator, $expected_ids]) {
      $this->resetInsertedRows();

      $this->connection->insert('json')
        ->from($this->sampleSelect())
        ->jsonCondition('test_field', $jsonpath, $value, $operator)
        ->execute();

      $this->assertEqualsCanonicalizing(
        $expected_ids,
        array_keys($this->insertedRows()),
        "Wrong rows inserted for: $label",
      );
    }
  }

  /**
   * Tests that JSON conditions combine with AND, OR and regular conditions.
   */
  public function testJsonConditionInsertCombined(): void {
    // AND: number >= 5 AND boolean = true → row 1 only.
    $this->connection->insert('json')
      ->from($this->sampleSelect())
      ->jsonCondition('test_field', '$.number', 5, '>=')
      ->jsonCondition('test_field', '$.boolean', TRUE, '=')
      ->execute();
    $this->assertEquals([1], array_keys($this->insertedRows()));

    // OR: key = "some text" OR number = 5 → rows 1, 3.
    $this->resetInsertedRows();
    $select = $this->sampleSelect();
    $or = $select->orConditionGroup();
    $or->jsonCondition('test_field', '$.key', 'some text');
    $or->jsonCondition('test_field', '$.number', 5);
    $this->connection->insert('json')
      ->from($select->condition($or))
      ->execute();
    $this->assertEqualsCanonicalizing([1, 3], array_keys($this->insertedRows()));

    // Mixed: a JSON condition AND a condition on a regular column.
    $this->resetInsertedRows();
    $select = $this->sampleSelect();
    $select->condition('j.id', 3);
    $this->connection->insert('json')
      ->from($select)
      ->jsonCondition('test_field', '$.number', 10, '=')
      ->execute();
    $this->assertEquals([3], array_keys($this->insertedRows()));
  }

  /**
   * Tests inserting a value extracted from a JSON column.
   */
  public function testJsonExpression(): void {
    // The default cast returns text without the quotes of the JSON string.
    $this->connection->insert('json')
      ->from($this->sampleSelect())
      ->jsonExpression('name', 'test_field', '$.key')
      ->jsonCondition('test_field', '$.key', NULL, 'HAS KEY')
      ->execute();
    $this->assertEquals(
      [3 => 'some text', 4 => 'some other text'],
      $this->insertedRows('name'),
    );

    // An integer cast inserts a number. The marker moves to the name column,
    // as the extracted value is written to the status column.
    $this->resetInsertedRows();
    $this->connection->insert('json')
      ->from($this->sampleSelect('name'))
      ->jsonExpression('status', 'test_field', '$.number', JsonCastType::Int)
      ->jsonCondition('test_field', '$.number', NULL, 'HAS KEY')
      ->execute();
    $statuses = array_map('intval', $this->insertedRows('status', 'name'));
    $this->assertEquals([1 => 5, 2 => 10, 3 => 10], $statuses);

    // A float cast inserts a fractional number.
    $this->resetInsertedRows();
    $this->connection->insert('json')
      ->from($this->sampleSelect())
      ->jsonExpression('score', 'test_field', '$.float', JsonCastType::Float)
      ->jsonCondition('test_field', '$.float', NULL, 'HAS KEY')
      ->execute();
    $scores = $this->insertedRows('score');
    $this->assertEqualsCanonicalizing([5, 8], array_keys($scores));
    $this->assertEquals(1.5, (float) $scores[5]);
    $this->assertEquals(3.14, (float) $scores[8]);
  }

  /**
   * Tests that a boolean cast inserts 1 or 0 on every backend.
   */
  public function testJsonExpressionBooleanCast(): void {
    $this->connection->insert('json')
      ->from($this->sampleSelect('name'))
      ->jsonExpression('status', 'test_field', '$.boolean', JsonCastType::Bool)
      ->jsonCondition('test_field', '$.boolean', NULL, 'HAS KEY')
      ->execute();

    // Rows 1 and 4 hold true, row 2 holds false.
    $statuses = array_map('intval', $this->insertedRows('status', 'name'));
    $this->assertEquals([1 => 1, 2 => 0, 4 => 1], $statuses);
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
      $this->resetInsertedRows();

      $select = $this->sampleSelect();
      $select->condition('j.id', $id);
      $this->connection->insert('json')
        ->from($select)
        ->jsonExpression('name', 'test_field', $jsonpath)
        ->execute();

      $this->assertSame([$id => $expected], $this->insertedRows('name'), "Wrong value for: $label");
    }
  }

  /**
   * Tests that a JSON expression inserts NULL when the path does not exist.
   */
  public function testJsonExpressionMissingPath(): void {
    $this->connection->insert('json')
      ->from($this->sampleSelect())
      ->jsonExpression('name', 'test_field', '$.nonexistent')
      ->execute();

    $names = $this->insertedRows('name');
    $this->assertCount(static::LAST_SAMPLE_ID, $names);
    $this->assertSame([], array_filter($names, fn ($name) => $name !== NULL));
  }

  /**
   * Tests that the columns of an insert follow the names of the select query.
   */
  public function testJsonExpressionFieldOrder(): void {
    $select = $this->connection->select('json', 'j');
    $select->condition('j.id', 3);
    // The extraction is added before the field it is inserted next to, so the
    // target column can only be found by name.
    $insert = $this->connection->insert('json')->from($select);
    $insert->jsonExpression('name', 'test_field', '$.key');
    $select->addField('j', 'id', 'status');
    $insert->execute();

    $this->assertEquals([3 => 'some text'], $this->insertedRows('name'));
  }

  /**
   * Tests that a JSON expression rejects an invalid jsonpath.
   */
  public function testJsonExpressionPathValidation(): void {
    $this->expectException(InvalidQueryException::class);
    $this->connection->insert('json')
      ->from($this->sampleSelect())
      ->jsonExpression('name', 'test_field', "$.key' OR 1=1--");
  }

  /**
   * Tests that a JSON expression rejects a wildcard path.
   *
   * A wildcard matches any number of values, and an inserted column takes one.
   */
  public function testJsonExpressionWildcard(): void {
    $this->expectException(InvalidQueryException::class);
    $this->expectExceptionMessage('contains a wildcard, which is not supported when casting a value');
    $this->connection->insert('json')
      ->from($this->sampleSelect())
      ->jsonExpression('name', 'test_field', '$.deps.*.status');
  }

  /**
   * Tests that a second expression for the same column is rejected.
   */
  public function testJsonExpressionDuplicateField(): void {
    $insert = $this->connection->insert('json')
      ->from($this->sampleSelect())
      ->jsonExpression('name', 'test_field', '$.key');

    $this->expectException(InvalidQueryException::class);
    $this->expectExceptionMessage('The select query already has an expression named "name".');
    $insert->jsonExpression('name', 'test_field', '$.number');
  }

  /**
   * Tests that a JSON condition needs a select query to insert the result of.
   */
  public function testJsonConditionWithoutFromQuery(): void {
    $this->expectException(InvalidQueryException::class);
    $this->expectExceptionMessage('An insert query only takes a JSON condition when it inserts the result of a select query. Call from() first.');
    $this->connection->insert('json')
      ->jsonCondition('test_field', '$.number', 5);
  }

  /**
   * Tests that a JSON expression needs a select query to insert the result of.
   */
  public function testJsonExpressionWithoutFromQuery(): void {
    $this->expectException(InvalidQueryException::class);
    $this->expectExceptionMessage('An insert query only takes a JSON expression when it inserts the result of a select query. Call from() first.');
    $this->connection->insert('json')
      ->jsonExpression('name', 'test_field', '$.key');
  }

}
