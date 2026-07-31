<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\InvalidQueryException;
use Drupal\Core\Database\Query\JsonCastType;
use Drupal\Core\Database\Query\JsonCondition;
use Drupal\Core\Database\Query\SelectExtender;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// cspell:ignore handies uncompahgre

/**
 * Tests the Select query builder.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class SelectTest extends DatabaseTestBase {

  /**
   * Tests rudimentary SELECT statements.
   */
  public function testSimpleSelect(): void {
    $query = $this->connection->select('test');
    $query->addField('test', 'name');
    $query->addField('test', 'age', 'age');
    $num_records = $query->countQuery()->execute()->fetchField();

    $this->assertEquals(4, $num_records, 'Returned the correct number of rows.');
  }

  /**
   * Tests rudimentary SELECT statement with a COMMENT.
   */
  public function testSimpleComment(): void {
    $query = $this->connection->select('test')->comment('Testing query comments');
    $query->addField('test', 'name');
    $query->addField('test', 'age', 'age');
    $result = $query->execute();

    $records = $result->fetchAll();

    $query = (string) $query;
    $expected = "/* Testing query comments */";

    $this->assertCount(4, $records, 'Returned the correct number of rows.');
    $this->assertStringContainsString($expected, $query, 'The flattened query contains the comment string.');
  }

  /**
   * Tests query COMMENT system against vulnerabilities.
   */
  public function testVulnerableComment(): void {
    $query = $this->connection->select('test')->comment('Testing query comments */ SELECT nid FROM {node}; --');
    $query->addField('test', 'name');
    $query->addField('test', 'age', 'age');
    $result = $query->execute();

    $records = $result->fetchAll();

    $query = (string) $query;
    $expected = "/* Testing query comments  * / SELECT nid FROM {node}. -- */";

    // Check the returned number of rows.
    $this->assertCount(4, $records);
    // Check that the flattened query contains the sanitized comment string.
    $this->assertStringContainsString($expected, $query);

    $connection = Database::getConnection();
    foreach ($this->makeCommentsProvider() as $test_set) {
      [$expected, $comments] = $test_set;
      $this->assertEquals($expected, $connection->makeComment($comments));
    }
  }

  /**
   * Provides expected and input values for testVulnerableComment().
   */
  public function makeCommentsProvider(): array {
    return [
      [
        '/*  */ ',
        [''],
      ],
      // Try and close the comment early.
      [
        '/* Exploit  * / DROP TABLE node. -- */ ',
        ['Exploit */ DROP TABLE node; --'],
      ],
      // Variations on comment closing.
      [
        '/* Exploit  * / * / DROP TABLE node. -- */ ',
        ['Exploit */*/ DROP TABLE node; --'],
      ],
      [
        '/* Exploit  *  * // DROP TABLE node. -- */ ',
        ['Exploit **// DROP TABLE node; --'],
      ],
      // Try closing the comment in the second string which is appended.
      [
        '/* Exploit  * / DROP TABLE node. --. Another try  * / DROP TABLE node. -- */ ',
        ['Exploit */ DROP TABLE node; --', 'Another try */ DROP TABLE node; --'],
      ],
    ];
  }

  /**
   * Tests basic conditionals on SELECT statements.
   */
  public function testSimpleSelectConditional(): void {
    $query = $this->connection->select('test');
    $name_field = $query->addField('test', 'name');
    $age_field = $query->addField('test', 'age', 'age');
    $query->condition('age', 27);
    $result = $query->execute();

    // Check that the aliases are being created the way we want.
    $this->assertEquals('name', $name_field, 'Name field alias is correct.');
    $this->assertEquals('age', $age_field, 'Age field alias is correct.');

    // Ensure that we got the right record.
    $record = $result->fetch();
    $this->assertEquals('George', $record->{$name_field}, 'Fetched name is correct.');
    $this->assertEquals(27, $record->{$age_field}, 'Fetched age is correct.');
  }

  /**
   * Tests SELECT statements with expressions.
   */
  public function testSimpleSelectExpression(): void {
    $query = $this->connection->select('test');
    $name_field = $query->addField('test', 'name');
    $age_field = $query->addExpression("[age]*2", 'double_age');
    $query->condition('age', 27);
    $result = $query->execute();

    // Check that the aliases are being created the way we want.
    $this->assertEquals('name', $name_field, 'Name field alias is correct.');
    $this->assertEquals('double_age', $age_field, 'Age field alias is correct.');

    // Ensure that we got the right record.
    $record = $result->fetch();
    $this->assertEquals('George', $record->{$name_field}, 'Fetched name is correct.');
    $this->assertEquals(27 * 2, $record->{$age_field}, 'Fetched age expression is correct.');
  }

  /**
   * Tests SELECT statements with multiple expressions.
   */
  public function testSimpleSelectExpressionMultiple(): void {
    $query = $this->connection->select('test');
    $name_field = $query->addField('test', 'name');
    $age_double_field = $query->addExpression("[age]*2");
    $age_triple_field = $query->addExpression("[age]*3");
    $query->condition('age', 27);
    $result = $query->execute();

    // Check that the aliases are being created the way we want.
    $this->assertEquals('expression', $age_double_field, 'Double age field alias is correct.');
    $this->assertEquals('expression_2', $age_triple_field, 'Triple age field alias is correct.');

    // Ensure that we got the right record.
    $record = $result->fetch();
    $this->assertEquals('George', $record->{$name_field}, 'Fetched name is correct.');
    $this->assertEquals(27 * 2, $record->{$age_double_field}, 'Fetched double age expression is correct.');
    $this->assertEquals(27 * 3, $record->{$age_triple_field}, 'Fetched triple age expression is correct.');
  }

  /**
   * Tests adding multiple fields to a SELECT statement at the same time.
   */
  public function testSimpleSelectMultipleFields(): void {
    $record = $this->connection->select('test')
      ->fields('test', ['id', 'name', 'age', 'job'])
      ->condition('age', 27)
      ->execute()->fetchObject();

    // Check that all fields we asked for are present.
    $this->assertNotNull($record->id, 'ID field is present.');
    $this->assertNotNull($record->name, 'Name field is present.');
    $this->assertNotNull($record->age, 'Age field is present.');
    $this->assertNotNull($record->job, 'Job field is present.');

    // Ensure that we got the right record.
    // Check that all fields we asked for are present.
    $this->assertEquals(2, $record->id, 'ID field has the correct value.');
    $this->assertEquals('George', $record->name, 'Name field has the correct value.');
    $this->assertEquals(27, $record->age, 'Age field has the correct value.');
    $this->assertEquals('Singer', $record->job, 'Job field has the correct value.');
  }

  /**
   * Tests adding all fields from a given table to a SELECT statement.
   */
  public function testSimpleSelectAllFields(): void {
    $record = $this->connection->select('test')
      ->fields('test')
      ->condition('age', 27)
      ->execute()->fetchObject();

    // Check that all fields we asked for are present.
    $this->assertNotNull($record->id, 'ID field is present.');
    $this->assertNotNull($record->name, 'Name field is present.');
    $this->assertNotNull($record->age, 'Age field is present.');
    $this->assertNotNull($record->job, 'Job field is present.');

    // Ensure that we got the right record.
    // Check that all fields we asked for are present.
    $this->assertEquals(2, $record->id, 'ID field has the correct value.');
    $this->assertEquals('George', $record->name, 'Name field has the correct value.');
    $this->assertEquals(27, $record->age, 'Age field has the correct value.');
    $this->assertEquals('Singer', $record->job, 'Job field has the correct value.');
  }

  /**
   * Tests that a comparison with NULL is always FALSE.
   */
  public function testNullCondition(): void {
    $this->ensureSampleDataNull();

    $names = $this->connection->select('test_null', 'tn')
      ->fields('tn', ['name'])
      ->condition('age')
      ->execute()->fetchCol();

    $this->assertCount(0, $names, 'No records found when comparing to NULL.');
  }

  /**
   * Tests that we can find a record with a NULL value.
   */
  public function testIsNullCondition(): void {
    $this->ensureSampleDataNull();

    $names = $this->connection->select('test_null', 'tn')
      ->fields('tn', ['name'])
      ->isNull('age')
      ->execute()->fetchCol();

    $this->assertCount(1, $names, 'Correct number of records found with NULL age.');
    $this->assertEquals('Ernie', $names[0], 'Correct record returned for NULL age.');
  }

  /**
   * Tests that we can find a record without a NULL value.
   */
  public function testIsNotNullCondition(): void {
    $this->ensureSampleDataNull();

    $names = $this->connection->select('test_null', 'tn')
      ->fields('tn', ['name'])
      ->isNotNull('tn.age')
      ->orderBy('name')
      ->execute()->fetchCol();

    $this->assertCount(2, $names, 'Correct number of records found withNOT NULL age.');
    $this->assertEquals('Gonzo', $names[0], 'Correct record returned for NOT NULL age.');
    $this->assertEquals('Kermit', $names[1], 'Correct record returned for NOT NULL age.');
  }

  /**
   * Tests that we can force a query to return an empty result.
   */
  public function testAlwaysFalseCondition(): void {
    $names = $this->connection->select('test', 'test')
      ->fields('test', ['name'])
      ->condition('age', 27)
      ->execute()->fetchCol();

    $this->assertCount(1, $names);
    $this->assertSame($names[0], 'George');

    $names = $this->connection->select('test', 'test')
      ->fields('test', ['name'])
      ->condition('age', 27)
      ->alwaysFalse()
      ->execute()->fetchCol();

    $this->assertCount(0, $names);
  }

  /**
   * Tests that we can force an extended query to return an empty result.
   */
  public function testExtenderAlwaysFalseCondition(): void {
    $names = $this->connection->select('test', 'test')
      ->extend(SelectExtender::class)
      ->fields('test', ['name'])
      ->condition('age', 27)
      ->execute()->fetchCol();

    $this->assertCount(1, $names);
    $this->assertSame($names[0], 'George');

    $names = $this->connection->select('test', 'test')
      ->extend(SelectExtender::class)
      ->fields('test', ['name'])
      ->condition('age', 27)
      ->alwaysFalse()
      ->execute()->fetchCol();

    $this->assertCount(0, $names);
  }

  /**
   * Tests that we can UNION multiple Select queries together.
   *
   * This is semantically equal to UNION DISTINCT, so we don't explicitly test
   * that.
   */
  public function testUnion(): void {
    $query_1 = $this->connection->select('test', 't')
      ->fields('t', ['name'])
      ->condition('age', [27, 28], 'IN');

    $query_2 = $this->connection->select('test', 't')
      ->fields('t', ['name'])
      ->condition('age', 28);

    $query_1->union($query_2);

    $names = $query_1->execute()->fetchCol();

    // Ensure we only get 2 records.
    $this->assertCount(2, $names, 'UNION correctly discarded duplicates.');

    $this->assertEqualsCanonicalizing(['George', 'Ringo'], $names);
  }

  /**
   * Tests that we can UNION ALL multiple SELECT queries together.
   */
  public function testUnionAll(): void {
    $query_1 = $this->connection->select('test', 't')
      ->fields('t', ['name'])
      ->condition('age', [27, 28], 'IN');

    $query_2 = $this->connection->select('test', 't')
      ->fields('t', ['name'])
      ->condition('age', 28);

    $query_1->union($query_2, 'ALL');

    $names = $query_1->execute()->fetchCol();

    // Ensure we get all 3 records.
    $this->assertCount(3, $names, 'UNION ALL correctly preserved duplicates.');

    $this->assertEquals('George', $names[0], 'First query returned correct first name.');
    $this->assertEquals('Ringo', $names[1], 'Second query returned correct second name.');
    $this->assertEquals('Ringo', $names[2], 'Third query returned correct name.');
  }

  /**
   * Tests that we can get a count query for a UNION Select query.
   */
  public function testUnionCount(): void {
    $query_1 = $this->connection->select('test', 't')
      ->fields('t', ['name', 'age'])
      ->condition('age', [27, 28], 'IN');

    $query_2 = $this->connection->select('test', 't')
      ->fields('t', ['name', 'age'])
      ->condition('age', 28);

    $query_1->union($query_2, 'ALL');
    $names = $query_1->execute()->fetchCol();
    $count = (int) $query_1->countQuery()->execute()->fetchField();

    // Ensure the counts match.
    $this->assertSame(count($names), $count, "The count query's result matched the number of rows in the UNION query.");
  }

  /**
   * Tests that we can UNION multiple Select queries together and set the ORDER.
   */
  public function testUnionOrder(): void {
    // This gives George and Ringo.
    $query_1 = $this->connection->select('test', 't')
      ->fields('t', ['name'])
      ->condition('age', [27, 28], 'IN');

    // This gives Paul.
    $query_2 = $this->connection->select('test', 't')
      ->fields('t', ['name'])
      ->condition('age', 26);

    $query_1->union($query_2);
    $query_1->orderBy('name', 'DESC');

    $names = $query_1->execute()->fetchCol();

    // Ensure we get all 3 records.
    $this->assertCount(3, $names, 'UNION returned rows from both queries.');

    // Ensure that the names are in the correct reverse alphabetical order,
    // regardless of which query they came from.
    $this->assertEquals('Ringo', $names[0], 'First query returned correct name.');
    $this->assertEquals('Paul', $names[1], 'Second query returned correct name.');
    $this->assertEquals('George', $names[2], 'Third query returned correct name.');
  }

  /**
   * Tests that we can UNION multiple Select queries together with and a LIMIT.
   */
  public function testUnionOrderLimit(): void {
    // This gives George and Ringo.
    $query_1 = $this->connection->select('test', 't')
      ->fields('t', ['name'])
      ->condition('age', [27, 28], 'IN');

    // This gives Paul.
    $query_2 = $this->connection->select('test', 't')
      ->fields('t', ['name'])
      ->condition('age', 26);

    $query_1->union($query_2);
    $query_1->orderBy('name', 'DESC');
    $query_1->range(0, 2);

    $names = $query_1->execute()->fetchCol();

    // Ensure we get all only 2 of the 3 records.
    $this->assertCount(2, $names, 'UNION with a limit returned rows from both queries.');

    // Ensure that the names are in the correct reverse alphabetical order,
    // regardless of which query they came from.
    $this->assertEquals('Ringo', $names[0], 'First query returned correct name.');
    $this->assertEquals('Paul', $names[1], 'Second query returned correct name.');
  }

  /**
   * Tests that random ordering of queries works.
   *
   * We take the approach of testing the Drupal layer only, rather than trying
   * to test that the database's random number generator actually produces
   * random queries (which is very difficult to do without an unacceptable risk
   * of the test failing by accident).
   *
   * Therefore, in this test we simply run the same query twice and assert that
   * the two results are reordered versions of each other (as well as of the
   * same query without the random ordering). It is reasonable to assume that
   * if we run the same select query twice and the results are in a different
   * order each time, the only way this could happen is if we have successfully
   * triggered the database's random ordering functionality.
   */
  public function testRandomOrder(): void {
    // Use 52 items, so the chance that this test fails by accident will be the
    // same as the chance that a deck of cards will come out in the same order
    // after shuffling it (in other words, nearly impossible).
    $number_of_items = 52;
    while ($this->connection->query("SELECT MAX([id]) FROM {test}")->fetchField() < $number_of_items) {
      $this->connection->insert('test')->fields(['name' => $this->randomMachineName()])->execute();
    }

    // First select the items in order and make sure we get an ordered list.
    $expected_ids = range(1, $number_of_items);
    $ordered_ids = $this->connection->select('test', 't')
      ->fields('t', ['id'])
      ->range(0, $number_of_items)
      ->orderBy('id')
      ->execute()
      ->fetchCol();
    $this->assertEquals($expected_ids, $ordered_ids, 'A query without random ordering returns IDs in the correct order.');

    // Now perform the same query, but instead choose a random ordering. We
    // expect this to contain a differently ordered version of the original
    // result.
    $randomized_ids = $this->connection->select('test', 't')
      ->fields('t', ['id'])
      ->range(0, $number_of_items)
      ->orderRandom()
      ->execute()
      ->fetchCol();
    $this->assertNotEquals($ordered_ids, $randomized_ids, 'A query with random ordering returns an unordered set of IDs.');
    $sorted_ids = $randomized_ids;
    sort($sorted_ids);
    $this->assertEquals($ordered_ids, $sorted_ids, 'After sorting the random list, the result matches the original query.');

    // Now perform the exact same query again, and make sure the order is
    // different.
    $randomized_ids_second_set = $this->connection->select('test', 't')
      ->fields('t', ['id'])
      ->range(0, $number_of_items)
      ->orderRandom()
      ->execute()
      ->fetchCol();
    $this->assertNotEquals($randomized_ids, $randomized_ids_second_set, 'Performing the query with random ordering a second time returns IDs in a different order.');
    $sorted_ids_second_set = $randomized_ids_second_set;
    sort($sorted_ids_second_set);
    $this->assertEquals($sorted_ids, $sorted_ids_second_set, 'After sorting the second random list, the result matches the sorted version of the first random list.');
  }

  /**
   * Data provider for testRegularExpressionCondition().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - the expected result of the query
   *     - the table column to do the search on.
   *     - the regular expression pattern to search for.
   *     - the regular expression operator 'REGEXP' or 'NOT REGEXP'.
   */
  public static function providerRegularExpressionCondition(): array {
    return [
      [['John'], 'name', 'hn$', 'REGEXP'],
      [['Paul'], 'name', '^Pau', 'REGEXP'],
      [['George', 'Ringo'], 'name', 'Ringo|George', 'REGEXP'],
      [['Pete'], 'job', '#Drummer', 'REGEXP'],
      [[], 'job', '#Singer', 'REGEXP'],
      [['Paul', 'Pete'], 'age', '2[6]', 'REGEXP'],

      [['George', 'Paul', 'Pete', 'Ringo'], 'name', 'hn$', 'NOT REGEXP'],
      [['George', 'John', 'Pete', 'Ringo'], 'name', '^Pau', 'NOT REGEXP'],
      [['John', 'Paul', 'Pete'], 'name', 'Ringo|George', 'NOT REGEXP'],
      [['George', 'John', 'Paul', 'Ringo'], 'job', '#Drummer', 'NOT REGEXP'],
      [['George', 'John', 'Paul', 'Pete', 'Ringo'], 'job', '#Singer', 'NOT REGEXP'],
      [['George', 'John', 'Ringo'], 'age', '2[6]', 'NOT REGEXP'],
    ];
  }

  /**
   * Tests that filter by 'REGEXP' and 'NOT REGEXP' works as expected.
   */
  #[DataProvider('providerRegularExpressionCondition')]
  public function testRegularExpressionCondition(array $expected, string $column, string $pattern, string $operator): void {
    $database = $this->container->get('database');
    $database->insert('test')
      ->fields([
        'name' => 'Pete',
        'age' => 26,
        'job' => '#Drummer',
      ])
      ->execute();

    $query = $database->select('test', 't');
    $query->addField('t', 'name');
    $query->condition("t.$column", $pattern, $operator);
    $result = $query->execute()->fetchCol();
    sort($result);

    $this->assertEquals($expected, $result);
  }

  /**
   * Tests that aliases are renamed when they are duplicates.
   */
  public function testSelectDuplicateAlias(): void {
    $query = $this->connection->select('test', 't');
    $alias1 = $query->addField('t', 'name', 'the_alias');
    $alias2 = $query->addField('t', 'age', 'the_alias');
    $this->assertNotSame($alias1, $alias2, 'Duplicate aliases are renamed.');
  }

  /**
   * Tests that an invalid count query throws an exception.
   */
  public function testInvalidSelectCount(): void {
    $this->expectException(DatabaseExceptionWrapper::class);
    // This query will fail because the table does not exist.
    $this->connection->select('some_table_that_does_not_exist', 't')
      ->fields('t')
      ->countQuery()
      ->execute();
  }

  /**
   * Tests thrown exception for IN query conditions with an empty array.
   */
  public function testEmptyInCondition(): void {
    try {
      $this->connection->select('test', 't')
        ->fields('t')
        ->condition('age', [], 'IN')
        ->execute();

      $this->fail('Expected exception not thrown');
    }
    catch (InvalidQueryException $e) {
      $this->assertEquals("Query condition 'age IN ()' cannot be empty.", $e->getMessage());
    }

    try {
      $this->connection->select('test', 't')
        ->fields('t')
        ->condition('age', [], 'NOT IN')
        ->execute();

      $this->fail('Expected exception not thrown');
    }
    catch (InvalidQueryException $e) {
      $this->assertEquals("Query condition 'age NOT IN ()' cannot be empty.", $e->getMessage());
    }
  }

  /**
   * Data provider for testNonArrayOperatorWithArrayValueCondition().
   *
   * @return array[]
   *   Array of non array compatible operators and its value in the expected
   *   exception message.
   */
  public static function providerNonArrayOperatorWithArrayValueCondition(): array {
    return [
      '=' => ['=', '='],
      '>' => ['>', '>'],
      '<' => ['<', '<'],
      '>=' => ['>=', '>='],
      '<=' => ['<=', '<='],
      'IS NULL' => ['IS NULL', 'IS NULL'],
      'IS NOT NULL' => ['IS NOT NULL', 'IS NOT NULL'],
      'Empty string' => ['', '='],
      'Not set' => [NULL, '='],
    ];
  }

  /**
   * Tests thrown exception for non array operator conditions with array value.
   */
  #[DataProvider('providerNonArrayOperatorWithArrayValueCondition')]
  public function testNonArrayOperatorWithArrayValueCondition(?string $operator, string $operator_in_exception_message): void {
    $this->expectException(InvalidQueryException::class);
    $this->expectExceptionMessageIs("Query condition 'age " . $operator_in_exception_message . " 26, 27' must have an array compatible operator.");

    $this->connection->select('test', 't')
      ->fields('t')
      ->condition('age', [26, 27], $operator)
      ->execute();
  }

  /**
   * Data provider of JSON condition test cases.
   *
   * @return array[]
   *   Test cases.
   */
  public static function providerJsonConditionOperators(): array {
    return [
      // Scalar comparisons.
      'Equals' => ['$.number', 10, '=', [2, 3]],
      'Text equals' => ['$.key', 'some text', '=', [3]],
      'Not equals <>' => ['$.number', 10, '<>', [1]],
      'Not equals !=' => ['$.number', 10, '!=', [1]],
      'LT with matching' => ['$.number', 10, '<', [1]],
      'LT with no matching' => ['$.number', 5, '<', []],
      'LTE with matching' => ['$.number', 5, '<=', [1]],
      'LTE with no matching' => ['$.number', -10, '<=', []],
      'GT with matching' => ['$.number', -2, '>', [1, 2, 3]],
      'GT with subset matching' => ['$.number', 7, '>', [2, 3]],
      'GTE with matching' => ['$.number', 10, '>=', [2, 3]],
      'GTE with boundary' => ['$.number', 5, '>=', [1, 2, 3]],

      // Boolean comparisons.
      'Boolean TRUE' => ['$.boolean', TRUE, '=', [1, 4]],
      'Boolean FALSE' => ['$.boolean', FALSE, '=', [2]],
      'Boolean invalid condition' => ['$.boolean', 'invalid string argument', '=', []],
      'Boolean IN TRUE' => ['$.boolean', [TRUE], 'IN', [1, 4]],
      'Boolean IN FALSE' => ['$.boolean', [FALSE], 'IN', [2]],
      'Boolean IN both' => ['$.boolean', [TRUE, FALSE], 'IN', [1, 2, 4]],
      'Boolean NOT IN TRUE' => ['$.boolean', [TRUE], 'NOT IN', [2]],

      // Type mismatch.
      'Non-matching text' => ['$.key', 'nothing to see here', '=', []],
      'String instead of number' => ['$.number', 'stringy', '=', []],

      // IN / NOT IN.
      'IN with matching' => ['$.number', [5, 10], 'IN', [1, 2, 3]],
      'IN with partial matching' => ['$.number', [5], 'IN', [1]],
      'IN with no matching' => ['$.number', [99, 100], 'IN', []],
      'NOT IN with matching' => ['$.number', [10], 'NOT IN', [1]],
      'IN with empty array' => ['$.number', [], 'IN', []],
      'NOT IN with empty array' => ['$.number', [], 'NOT IN', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]],

      // JSON_CONTAINS.
      'Array value with full containment' => ['$.array', [32, 41], 'JSON_CONTAINS', [5]],
      'Array value with partial containment' => ['$.array', [41], 'JSON_CONTAINS', [5]],
      'Array value with no containment' => ['$.array', [81235], 'JSON_CONTAINS', []],
      'Associative array value with containment' => [
        '$.associative', ['mountain' => 'Handies'], 'JSON_CONTAINS', [6],
      ],
      'Associative array value with no containment' => [
        '$.associative', ['mountain' => 'Uncompahgre'], 'JSON_CONTAINS', [],
      ],

      // String LIKE operators.
      'String CONTAINS matching' => ['$.key', 'some', 'CONTAINS', [3, 4]],
      'String CONTAINS partial' => ['$.key', 'other', 'CONTAINS', [4]],
      'String CONTAINS no match' => ['$.key', 'xyz', 'CONTAINS', []],
      'STARTS_WITH matching' => ['$.key', 'some', 'STARTS_WITH', [3, 4]],
      'STARTS_WITH no match' => ['$.key', 'xyz', 'STARTS_WITH', []],
      'ENDS_WITH matching' => ['$.key', 'text', 'ENDS_WITH', [3, 4]],
      'ENDS_WITH partial match' => ['$.key', 'other text', 'ENDS_WITH', [4]],

      // BETWEEN / NOT BETWEEN.
      'BETWEEN matching integers' => ['$.number', [5, 10], 'BETWEEN', [1, 2, 3]],
      'BETWEEN matching subset' => ['$.number', [6, 10], 'BETWEEN', [2, 3]],
      'BETWEEN no match' => ['$.number', [100, 200], 'BETWEEN', []],
      'NOT BETWEEN matching' => ['$.number', [6, 10], 'NOT BETWEEN', [1]],

      // IS NULL / IS NOT NULL (includes row 7 which has SQL NULL column).
      'IS NULL' => ['$.number', NULL, 'IS NULL', [4, 5, 6, 7, 8, 9, 10]],
      'IS NOT NULL' => ['$.number', NULL, 'IS NOT NULL', [1, 2, 3]],
      'IS NULL includes NULL column' => ['$.float', NULL, 'IS NULL', [1, 2, 3, 4, 6, 7, 9, 10]],
      'IS NOT NULL on float path' => ['$.float', NULL, 'IS NOT NULL', [5, 8]],

      // HAS KEY.
      'HAS KEY' => ['$.key', NULL, 'HAS KEY', [3, 4]],
      'HAS KEY no match' => ['$.nonexistent', NULL, 'HAS KEY', []],

      // Float comparisons.
      'Float equals' => ['$.float', 3.14, '=', [8]],
      'Float greater than' => ['$.float', 3.0, '>', [8]],
      'Float less than' => ['$.float', 4.0, '<', [5, 8]],
      'Float BETWEEN' => ['$.float', [3.0, 4.0], 'BETWEEN', [8]],

      // Nested paths.
      'Nested path equals' => ['$.nested.inner', 'deep value', '=', [8]],
      'Nested path no match' => ['$.nested.inner', 'wrong', '=', []],

      // Array indices.
      'Array index [0]' => ['$.array[0]', 32, '=', [5]],
      'Array index [1]' => ['$.array[1]', 41, '=', [5]],
      'Array index on items' => ['$.items[0]', 10, '=', [8]],

      // LIKE special characters (must be treated as literals, not wildcards).
      'CONTAINS percent literal' => ['$.special', '%', 'CONTAINS', [8]],
      'CONTAINS underscore literal' => ['$.special', '_', 'CONTAINS', [8]],
      'STARTS_WITH percent' => ['$.special', '100%', 'STARTS_WITH', [8]],
      'ENDS_WITH underscore' => ['$.special', '_sale', 'ENDS_WITH', [8]],
      'CONTAINS percent no false positive' => ['$.key', '%', 'CONTAINS', []],

      // Values with quotes must not break SQL string literals.
      'Quotes in JSON_CONTAINS value' => ['$.associative', ['mountain' => "' OR 1=1--"], 'JSON_CONTAINS', []],
      'Quotes in scalar value' => ['$.key', "' OR 1=1--", '=', []],
      'Quotes in CONTAINS value' => ['$.key', "' OR '1'='1", 'CONTAINS', []],

      // Hyphenated key names.
      'Hyphenated key equals' => ['$.hyphen-key', 'hyphen value', '=', [9]],
      'Hyphenated key no match' => ['$.hyphen-key', 'wrong', '=', []],
      'Hyphenated key IS NOT NULL' => ['$.hyphen-key', NULL, 'IS NOT NULL', [9]],
      'Hyphenated key IS NULL' => ['$.hyphen-key', NULL, 'IS NULL', [1, 2, 3, 4, 5, 6, 7, 8, 10]],
      'Nested hyphenated key' => ['$.nested-obj.inner-key', 42, '=', [9]],

      // Bracket notation with quoted keys.
      'Bracket notation equals' => ['$["hyphen-key"]', 'hyphen value', '=', [9]],
      'Bracket nested' => ['$["nested-obj"]["inner-key"]', 42, '=', [9]],
      'Bracket mixed with dot' => ['$.nested-obj["inner-key"]', 42, '=', [9]],
      'Bracket with single quote no injection' => ['$["key\' OR \'1\'=\'1"]', 'value', '=', []],

      // Wildcard path matching.
      'Wildcard nested equals' => ['$.deps.*.status', 'enabled', '=', [10]],
      'Wildcard nested equals other value' => ['$.deps.*.status', 'disabled', '=', [10]],
      'Wildcard nested not equals' => ['$.deps.*.status', 'enabled', '<>', [10]],
      'Wildcard nested no match' => ['$.deps.*.status', 'unknown', '=', []],
      'Wildcard top-level key' => ['$.*.inner', 'deep value', '=', [8]],
      'Wildcard numeric value' => ['$.deps.*.status', 42, '=', []],

      // Wildcard IN / NOT IN.
      'Wildcard IN matching one' => ['$.deps.*.status', ['enabled'], 'IN', [10]],
      'Wildcard IN matching both' => ['$.deps.*.status', ['enabled', 'disabled'], 'IN', [10]],
      'Wildcard IN no match' => ['$.deps.*.status', ['unknown'], 'IN', []],
      'Wildcard NOT IN excludes all' => ['$.deps.*.status', ['enabled', 'disabled'], 'NOT IN', []],
      'Wildcard NOT IN partial' => ['$.deps.*.status', ['enabled'], 'NOT IN', [10]],
      'Wildcard top-level IN' => ['$.*.inner', ['deep value', 'other'], 'IN', [8]],
    ];
  }

  /**
   * Test JSON conditions with supported operators.
   *
   * This test appears simple at first glance but is particularly important when
   * implementing JSON data types. Variable typing becomes far more sensitive
   * when matching JSON documents vs. other data which is stored directly in SQL
   * tables.
   */
  public function testJsonCondition(): void {
    foreach (static::providerJsonConditionOperators() as $label => [$jsonpath, $value, $operator, $expected_ids]) {
      $query = $this->connection->select('json', 'j');
      $query->addField('j', 'id');
      $query->jsonCondition('j.test_field', $jsonpath, $value, $operator);
      $ids = $query->execute()->fetchCol();
      $this->assertEqualsCanonicalizing($expected_ids, $ids, "Failed: $label");
    }
  }

  /**
   * Tests that JsonCondition validates its arguments.
   */
  public function testJsonConditionOperatorValidation(): void {
    $cases = [
      'unsupported operator' => ['field', '$.path', 'value', 'INVALID'],
      'IN requires array' => ['field', '$.path', 'string', 'IN'],
      'BETWEEN requires two' => ['field', '$.path', [1], 'BETWEEN'],
    ];
    foreach ($cases as $label => [$field, $path, $value, $op]) {
      try {
        new JsonCondition($field, $path, $value, $op);
        $this->fail("Expected exception for: $label");
      }
      catch (InvalidQueryException) {
        $this->addToAssertionCount(1);
      }
    }
  }

  /**
   * Tests that JsonExpression rejects invalid jsonpath expressions.
   */
  public function testJsonExpressionPathValidation(): void {
    $cases = [
      'no dollar prefix' => 'invalid',
      'single quote in path' => "$.key' OR 1=1--",
      'semicolon in path' => '$.key; DROP TABLE',
      'space in path' => '$.key OR true',
      'parenthesis in path' => '$.key)',
      'multiple wildcards' => '$.level1.*.level2.*.level3',
    ];
    foreach ($cases as $label => $path) {
      try {
        $this->connection->jsonExpression('test_field', $path);
        $this->fail("Expected exception for: $label");
      }
      catch (InvalidQueryException) {
        $this->addToAssertionCount(1);
      }
    }
  }

  /**
   * Tests combining JSON conditions with AND, OR, and regular conditions.
   */
  public function testJsonConditionCombined(): void {
    // AND: number >= 5 AND boolean = true → row 1 only.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonCondition('test_field', '$.number', 5, '>=');
    $query->jsonCondition('test_field', '$.boolean', TRUE, '=');
    $ids = array_map('intval', $query->execute()->fetchCol());
    sort($ids);
    $this->assertEquals([1], $ids);

    // OR: key = "some text" OR number = 5 → rows 1, 3.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $or = $query->orConditionGroup();
    $or->condition(new JsonCondition('test_field', '$.key', 'some text'));
    $or->condition(new JsonCondition('test_field', '$.number', 5));
    $query->condition($or);
    $ids = array_map('intval', $query->execute()->fetchCol());
    sort($ids);
    $this->assertEquals([1, 3], $ids);

    // Mixed: jsonCondition AND regular condition.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonCondition('test_field', '$.number', 10, '=');
    $query->condition('j.id', 3);
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEquals([3], $ids);
  }

  /**
   * Tests JSON ORDER BY with different types and directions.
   */
  public function testJsonOrderBy(): void {
    // Integer ASC: rows with $.number: 1 (5), 2 (10), 3 (10). NULLs last.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonOrderBy('test_field', '$.number', 'ASC', JsonCastType::Int);
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEquals(1, $ids[0]);
    $this->assertContains(2, array_slice($ids, 1, 2));
    $this->assertContains(3, array_slice($ids, 1, 2));
    // Rows without $.number sort as NULL; their order is undefined.
    $this->assertEqualsCanonicalizing([4, 5, 6, 7, 8, 9, 10], array_slice($ids, 3));

    // Integer DESC: 10 before 5, NULLs last.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonOrderBy('test_field', '$.number', 'DESC', JsonCastType::Int);
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertContains(2, array_slice($ids, 0, 2));
    $this->assertContains(3, array_slice($ids, 0, 2));
    $this->assertEquals(1, $ids[2]);
    $this->assertEqualsCanonicalizing([4, 5, 6, 7, 8, 9, 10], array_slice($ids, 3));

    // String ASC: rows with $.key: 3 ("some text"), 4 ("some other text").
    // "some other text" < "some text", so 4 then 3. NULLs last.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonOrderBy('test_field', '$.key', 'ASC');
    $ids = $query->execute()->fetchCol();
    $this->assertEquals(4, $ids[0]);
    $this->assertEquals(3, $ids[1]);
    $this->assertEqualsCanonicalizing([1, 2, 5, 6, 7, 8, 9, 10], array_slice($ids, 2));

    // String DESC: "some text" > "some other text", so 3 then 4. NULLs last.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonOrderBy('test_field', '$.key', 'DESC');
    $ids = $query->execute()->fetchCol();
    $this->assertEquals(3, $ids[0]);
    $this->assertEquals(4, $ids[1]);
    $this->assertEqualsCanonicalizing([1, 2, 5, 6, 7, 8, 9, 10], array_slice($ids, 2));

    // Float ASC: row 5 (1.5) before row 8 (3.14). NULLs last.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonOrderBy('test_field', '$.float', 'ASC', JsonCastType::Float);
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEquals(5, $ids[0]);
    $this->assertEquals(8, $ids[1]);
    $this->assertEqualsCanonicalizing([1, 2, 3, 4, 6, 7, 9, 10], array_slice($ids, 2));

    // Float DESC: row 8 (3.14) before row 5 (1.5). NULLs last.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonOrderBy('test_field', '$.float', 'DESC', JsonCastType::Float);
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEquals(8, $ids[0]);
    $this->assertEquals(5, $ids[1]);
    $this->assertEqualsCanonicalizing([1, 2, 3, 4, 6, 7, 9, 10], array_slice($ids, 2));
  }

  /**
   * Tests adding a JSON extraction to the SELECT list.
   */
  public function testAddJsonExpression(): void {
    // The default cast returns text without the quotes of the JSON string.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $alias = $query->addJsonExpression('test_field', '$.key', 'key_value');
    $this->assertEquals('key_value', $alias);
    $query->jsonCondition('test_field', '$.key', NULL, 'IS NOT NULL');
    $values = [];
    foreach ($query->execute() as $row) {
      $values[(int) $row->id] = $row->key_value;
    }
    $this->assertEquals([3 => 'some text', 4 => 'some other text'], $values);

    // An integer cast returns a number.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->addJsonExpression('test_field', '$.number', 'number_value', JsonCastType::Int);
    $query->condition('j.id', 1);
    $this->assertEquals(5, $query->execute()->fetchObject()->number_value);

    // The alias can be ordered by, and duplicate aliases are suffixed the same
    // way as in addExpression().
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $first = $query->addJsonExpression('test_field', '$.number', 'num', JsonCastType::Int);
    $second = $query->addJsonExpression('test_field', '$.float', 'num', JsonCastType::Float);
    $this->assertEquals('num', $first);
    $this->assertEquals('num_2', $second);
    $query->condition('j.id', 5);
    $row = $query->execute()->fetchObject();
    $this->assertNull($row->num);
    $this->assertEquals(1.5, $row->num_2);
  }

}
