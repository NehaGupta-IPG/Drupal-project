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

// cSpell:ignore bigserial

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
   * Test JSON conditions with supported operators.
   *
   * This test appears simple at first glance but is particularly important when
   * implementing JSON data types. Variable typing becomes far more sensitive
   * when matching JSON documents vs. other data which is stored directly in SQL
   * tables.
   *
   * @param string $jsonpath
   *   The jsonpath to compare.
   * @param mixed $value
   *   The value to compare against.
   * @param string $operator
   *   The comparison operator.
   * @param array $expected_ids
   *   The IDs of the sample rows the condition matches.
   */
  #[DataProvider('providerJsonConditionOperators')]
  public function testJsonCondition(string $jsonpath, mixed $value, string $operator, array $expected_ids): void {
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonCondition('j.test_field', $jsonpath, $value, $operator);
    $ids = $query->execute()->fetchCol();
    $this->assertEqualsCanonicalizing($expected_ids, $ids);
  }

  /**
   * Data provider of JSON conditions with invalid arguments.
   *
   * @return array[]
   *   Test cases, each an array of a field, a jsonpath, a value and an
   *   operator.
   */
  public static function providerJsonConditionOperatorValidation(): array {
    return [
      'unsupported operator' => ['field', '$.path', 'value', 'INVALID'],
      'IN requires array' => ['field', '$.path', 'string', 'IN'],
      'BETWEEN requires two' => ['field', '$.path', [1], 'BETWEEN'],
    ];
  }

  /**
   * Tests that JsonCondition validates its arguments.
   *
   * @param string $field
   *   The field holding the JSON document.
   * @param string $path
   *   The jsonpath to compare.
   * @param mixed $value
   *   The value to compare against.
   * @param string $operator
   *   The comparison operator.
   */
  #[DataProvider('providerJsonConditionOperatorValidation')]
  public function testJsonConditionOperatorValidation(string $field, string $path, mixed $value, string $operator): void {
    $this->expectException(InvalidQueryException::class);
    new JsonCondition($field, $path, $value, $operator);
  }

  /**
   * Data provider of invalid jsonpath expressions.
   *
   * @return array[]
   *   Test cases, each an array of a jsonpath.
   */
  public static function providerJsonExpressionPathValidation(): array {
    return [
      'no dollar prefix' => ['invalid'],
      'single quote in path' => ["$.key' OR 1=1--"],
      'semicolon in path' => ['$.key; DROP TABLE'],
      'space in path' => ['$.key OR true'],
      'parenthesis in path' => ['$.key)'],
      'multiple wildcards' => ['$.level1.*.level2.*.level3'],
      'multiple element wildcards' => ['$.level1[*].level2[*].level3'],
      'mixed wildcards' => ['$.level1[*].level2.*.level3'],
    ];
  }

  /**
   * Tests that JsonExpression rejects invalid jsonpath expressions.
   *
   * @param string $path
   *   The jsonpath to extract.
   */
  #[DataProvider('providerJsonExpressionPathValidation')]
  public function testJsonExpressionPathValidation(string $path): void {
    $this->expectException(InvalidQueryException::class);
    $this->connection->jsonExpression('test_field', $path);
  }

  /**
   * Data provider of operators that cannot expand a wildcard path.
   *
   * @return array[]
   *   Test cases, each an array of an operator and a value to compare.
   */
  public static function providerJsonWildcardUnsupportedOperator(): array {
    return [
      'JSON_CONTAINS' => ['JSON_CONTAINS', ['enabled']],
      'IS NULL' => ['IS NULL', NULL],
      'IS NOT NULL' => ['IS NOT NULL', NULL],
      'HAS KEY' => ['HAS KEY', NULL],
    ];
  }

  /**
   * Tests that operators which cannot expand a wildcard path reject one.
   *
   * A wildcard matches any number of values. The operators that test one
   * extracted value expand it into a row set, and ask whether any matched
   * value satisfies the test. The operators listed here read the document at
   * the path itself, which a wildcard does not address.
   *
   * @param string $operator
   *   The comparison operator.
   * @param mixed $value
   *   The value to compare against.
   *
   * @see \Drupal\Core\Database\Query\JsonCondition::WILDCARD_OPERATORS
   */
  #[DataProvider('providerJsonWildcardUnsupportedOperator')]
  public function testJsonWildcardUnsupportedOperator(string $operator, mixed $value): void {
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonCondition('test_field', '$.deps.*.status', $value, $operator);

    $this->expectException(InvalidQueryException::class);
    $this->expectExceptionMessage("JSON operator {$operator} does not support the wildcard");
    $query->execute();
  }

  /**
   * Tests that a comparison operator expands a wildcard path.
   *
   * @see \Drupal\KernelTests\Core\Database\SelectTest::testJsonWildcardUnsupportedOperator()
   */
  public function testJsonWildcardSupportedOperator(): void {
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonCondition('test_field', '$.deps.*.status', 'enabled');
    $this->assertSame([10], array_map('intval', $query->execute()->fetchCol()));
  }

  /**
   * Tests that the element wildcard is rejected by the same operators.
   *
   * @see \Drupal\KernelTests\Core\Database\SelectTest::testJsonWildcardUnsupportedOperator()
   */
  public function testJsonElementWildcardUnsupportedOperator(): void {
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonCondition('test_field', '$.revs[*].uid', NULL, 'HAS KEY');

    $this->expectException(InvalidQueryException::class);
    $this->expectExceptionMessage('JSON operator HAS KEY does not support the wildcard');
    $query->execute();
  }

  /**
   * Tests that the JSON expression surfaces reject a wildcard path.
   *
   * A SELECT expression and a sort key hold one value, so there is nowhere to
   * expand the wildcard into. Without this each backend degrades differently:
   * PostgreSQL looks for a literal "*" key, SQLite raises an error, and MySQL
   * returns the whole matched set.
   *
   * @see \Drupal\Core\Database\Query\JsonExpression::assertNoWildcard()
   */
  public function testJsonWildcardUnsupportedExpression(): void {
    $query = $this->connection->select('json', 'j');
    try {
      $query->addJsonExpression('test_field', '$.deps.*.status', 'st');
      $this->fail('Expected exception for addJsonExpression().');
    }
    catch (InvalidQueryException $e) {
      $this->assertStringContainsString('contains a wildcard, which is not supported when casting a value', $e->getMessage());
    }

    $query = $this->connection->select('json', 'j');
    $this->expectException(InvalidQueryException::class);
    $this->expectExceptionMessage('contains a wildcard');
    $query->jsonOrderBy('test_field', '$.deps.*.status');
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
    $or->jsonCondition('test_field', '$.key', 'some text');
    $or->jsonCondition('test_field', '$.number', 5);
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
   * Tests one JsonCondition instance used by more than one query.
   *
   * Each query numbers its placeholders from 0, so a condition object that is
   * compiled against a second query has to take the placeholder names of that
   * query. Keeping the names of the first compilation binds the values of one
   * condition to the placeholders of another.
   *
   * @see \Drupal\Core\Database\Query\JsonCondition::compile()
   */
  public function testJsonConditionReusedAcrossQueries(): void {
    $condition = $this->connection->jsonCondition('test_field', '$.number', 5);

    // The condition is the only one, so it takes placeholder 0.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->condition($condition);
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEquals([1], $ids);

    // Here the condition on the regular column takes placeholder 0, so the
    // JSON condition has to move to placeholder 1.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->condition('j.id', 1);
    $query->condition($condition);
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEquals([1], $ids);

    // The same instance in two groups of one query. Both compile against the
    // query, so the second use keeps its own placeholder.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $or = $query->orConditionGroup();
    $or->condition($condition);
    $or->condition('j.id', 4);
    $query->condition($or);
    $query->condition('j.id', 1, '>=');
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEqualsCanonicalizing([1, 4], $ids);

    // A count query compiles the conditions of the query it clones.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->condition('j.id', 1);
    $query->condition($condition);
    $this->assertSame('1', (string) $query->countQuery()->execute()->fetchField());
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEquals([1], $ids);
  }

  /**
   * Tests JSON conditions added to a condition group.
   *
   * @see \Drupal\Core\Database\Query\Condition::jsonCondition()
   */
  public function testJsonConditionInConditionGroup(): void {
    // A group mixes JSON conditions with conditions on regular columns. Row 1
    // holds 5 at $.number, row 4 is matched by its id.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $or = $query->orConditionGroup();
    $or->jsonCondition('test_field', '$.number', 5);
    $or->condition('j.id', 4);
    $query->condition($or);
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEqualsCanonicalizing([1, 4], $ids);

    // The method returns the group, so calls chain.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $group = $query->andConditionGroup();
    $this->assertSame($group, $group->jsonCondition('test_field', '$.number', 5, '>='));
    $query->condition($group->jsonCondition('test_field', '$.boolean', TRUE));
    $this->assertEquals([1], array_map('intval', $query->execute()->fetchCol()));

    // A group nested in another group keeps the JSON conditions grouped.
    // Boolean true holds for rows 1 and 4, "some" is contained in $.key of
    // rows 3 and 4, so the OR matches 1, 3 and 4. The id narrows that to 1
    // and 3.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $and = $query->andConditionGroup();
    $and->condition('j.id', [1, 2, 3], 'IN');
    $nested = $and->orConditionGroup();
    $nested->jsonCondition('test_field', '$.boolean', TRUE);
    $nested->jsonCondition('test_field', '$.key', 'some', 'CONTAINS');
    $query->condition($and->condition($nested));
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEqualsCanonicalizing([1, 3], $ids);

    // The placeholders a group generates do not collide with the ones of the
    // query it is added to. BETWEEN binds two of them and matches rows 1, 2
    // and 3; the id in the same group and the status outside it narrow that
    // to row 3.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->condition('j.status', 1);
    $and = $query->andConditionGroup();
    $and->jsonCondition('test_field', '$.number', [5, 10], 'BETWEEN');
    $and->condition('j.id', 3);
    $query->condition($and);
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEquals([3], $ids);
  }

  /**
   * Tests that a JSON condition in a group validates its arguments.
   */
  public function testJsonConditionInConditionGroupValidation(): void {
    $this->expectException(InvalidQueryException::class);
    $this->connection->condition('AND')
      ->jsonCondition('test_field', '$.number', 'string', 'IN');
  }

  /**
   * Tests JSON conditions in the HAVING clause.
   *
   * The queries group by the primary key, so every row forms a group of its
   * own and the JSON column stays functionally dependent on the grouped
   * column. HAVING then filters the same rows a WHERE would.
   *
   * @see \Drupal\Core\Database\Query\Select::havingJsonCondition()
   */
  public function testHavingJsonCondition(): void {
    // Rows 2 and 3 both hold 10 at $.number.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->groupBy('j.id');
    $query->groupBy('j.test_field');
    $this->assertSame($query, $query->havingJsonCondition('test_field', '$.number', 10, '='));
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEqualsCanonicalizing([2, 3], $ids);

    // Unary operators take no value. Rows 3 and 4 hold $.key.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->groupBy('j.id');
    $query->groupBy('j.test_field');
    $query->havingJsonCondition('test_field', '$.key', NULL, 'HAS KEY');
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEqualsCanonicalizing([3, 4], $ids);

    // Two HAVING conditions are ANDed. Boolean false holds for row 2 only.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->groupBy('j.id');
    $query->groupBy('j.test_field');
    $query->havingJsonCondition('test_field', '$.number', 10, '=');
    $query->havingJsonCondition('test_field', '$.boolean', FALSE, '=');
    $this->assertEquals([2], array_map('intval', $query->execute()->fetchCol()));

    // The placeholders of a HAVING condition collide neither with the ones of
    // the WHERE conditions nor with the ones of the other HAVING parts.
    // BETWEEN binds two placeholders and matches rows 1, 2 and 3; CONTAINS
    // binds one and matches rows 3 and 4.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->addExpression('COUNT(*)', 'total');
    $query->condition('j.status', 1);
    $query->jsonCondition('test_field', '$.number', [5, 10], 'BETWEEN');
    $query->groupBy('j.id');
    $query->groupBy('j.test_field');
    $query->having('COUNT(*) = :count', [':count' => 1]);
    $query->havingJsonCondition('test_field', '$.key', 'some', 'CONTAINS');
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEquals([3], $ids);
  }

  /**
   * Tests that a HAVING JSON condition validates its arguments.
   */
  public function testHavingJsonConditionValidation(): void {
    $this->expectException(InvalidQueryException::class);
    $this->connection->select('json', 'j')
      ->havingJsonCondition('test_field', '$.number', [1], 'BETWEEN');
  }

  /**
   * Tests JSON conditions on an extended query.
   *
   * @see \Drupal\Core\Database\Query\SelectExtender::jsonCondition()
   * @see \Drupal\Core\Database\Query\SelectExtender::havingJsonCondition()
   */
  public function testJsonConditionOnExtender(): void {
    // The extender returns itself, so a chain does not drop the extender
    // layer. Rows 1, 2 and 3 hold 5 or more at $.number.
    $query = $this->connection->select('json', 'j')->extend(SelectExtender::class);
    $query->addField('j', 'id');
    $this->assertSame($query, $query->jsonCondition('test_field', '$.number', 5, '>='));
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEqualsCanonicalizing([1, 2, 3], $ids);

    // An extender numbers placeholders with its own counter, so a JSON
    // condition and a condition on a regular column must not collide.
    // BETWEEN binds two placeholders and matches rows 1, 2 and 3; the id
    // narrows that to 2 and 3.
    $query = $this->connection->select('json', 'j')->extend(SelectExtender::class);
    $query->addField('j', 'id');
    $query->jsonCondition('test_field', '$.number', [5, 10], 'BETWEEN');
    $query->condition('j.id', [2, 3], 'IN');
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEqualsCanonicalizing([2, 3], $ids);

    // HAVING works the same way. The JSON column is grouped, so a HAVING
    // clause may name it.
    $query = $this->connection->select('json', 'j')->extend(SelectExtender::class);
    $query->addField('j', 'id');
    $query->groupBy('j.id');
    $query->groupBy('j.test_field');
    $this->assertSame($query, $query->havingJsonCondition('test_field', '$.number', 10, '='));
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEqualsCanonicalizing([2, 3], $ids);

    // One extender wrapping another passes both methods down to the query.
    // The WHERE keeps rows 1, 2 and 3, the HAVING keeps 2 and 3.
    $query = $this->connection->select('json', 'j')
      ->extend(SelectExtender::class)
      ->extend(SelectExtender::class);
    $query->addField('j', 'id');
    $query->groupBy('j.id');
    $query->groupBy('j.test_field');
    $this->assertSame($query, $query->jsonCondition('test_field', '$.number', 5, '>='));
    $this->assertSame($query, $query->havingJsonCondition('test_field', '$.key', NULL, 'HAS KEY'));
    $this->assertEquals([3], array_map('intval', $query->execute()->fetchCol()));
  }

  /**
   * Tests JSON expressions on an extended query.
   *
   * @see \Drupal\Core\Database\Query\SelectExtender::addJsonExpression()
   * @see \Drupal\Core\Database\Query\SelectExtender::jsonOrderBy()
   */
  public function testJsonExpressionOnExtender(): void {
    // The alias comes back from the wrapped query, so the extender returns a
    // string here, not itself.
    $query = $this->connection->select('json', 'j')->extend(SelectExtender::class);
    $query->addField('j', 'id');
    $alias = $query->addJsonExpression('test_field', '$.number', 'num', JsonCastType::Int);
    $this->assertSame('num', $alias);
    $query->condition('j.id', 1);
    $this->assertEquals(5, $query->execute()->fetchObject()->num);

    // Ordering returns the extender, so a chain does not drop the extender
    // layer. Row 1 holds 5 at $.number, rows 2 and 3 hold 10, and the rows
    // without the key sort last.
    $query = $this->connection->select('json', 'j')->extend(SelectExtender::class);
    $query->addField('j', 'id');
    $this->assertSame($query, $query->jsonOrderBy('test_field', '$.number', 'ASC', JsonCastType::Int));
    $ids = array_map('intval', $query->execute()->fetchCol());
    $this->assertEquals(1, $ids[0]);
    $this->assertEqualsCanonicalizing([2, 3], array_slice($ids, 1, 2));
    $this->assertEqualsCanonicalizing([4, 5, 6, 7, 8, 9, 10], array_slice($ids, 3));

    // One extender wrapping another passes both methods down to the query.
    // Rows 3 and 4 hold $.key, and "some other text" sorts before "some text".
    $query = $this->connection->select('json', 'j')
      ->extend(SelectExtender::class)
      ->extend(SelectExtender::class);
    $query->addField('j', 'id');
    $this->assertSame('key_value', $query->addJsonExpression('test_field', '$.key', 'key_value'));
    $this->assertSame($query, $query->jsonOrderBy('test_field', '$.key', 'ASC'));
    $query->jsonCondition('test_field', '$.key', NULL, 'IS NOT NULL');
    $values = [];
    foreach ($query->execute() as $row) {
      $values[(int) $row->id] = $row->key_value;
    }
    $this->assertSame([4 => 'some other text', 3 => 'some text'], $values);
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

  /**
   * Tests that an integer cast holds values wider than 32 bits.
   *
   * Entity and revision IDs come from bigserial columns, so the integer cast
   * has to be 64-bit on every driver.
   */
  public function testJsonIntCastIs64Bit(): void {
    // Above the 2147483647 limit of a 32-bit signed integer.
    $big = 4294967296;
    $this->connection->insert('json')
      ->fields([
        'id' => 11,
        'status' => 1,
        'test_field' => '{"big": ' . $big . '}',
      ])
      ->execute();

    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->addJsonExpression('test_field', '$.big', 'big_value', JsonCastType::Int);
    $query->condition('j.id', 11);
    $this->assertEquals($big, $query->execute()->fetchObject()->big_value);

    // Ordering casts the same way, so it must not error either. Row 11 holds
    // the only $.big value, the rows without the key sort as NULL.
    $query = $this->connection->select('json', 'j');
    $query->addField('j', 'id');
    $query->jsonOrderBy('test_field', '$.big', 'DESC', JsonCastType::Int);
    $this->assertEquals(11, (int) $query->execute()->fetchCol()[0]);
  }

}
