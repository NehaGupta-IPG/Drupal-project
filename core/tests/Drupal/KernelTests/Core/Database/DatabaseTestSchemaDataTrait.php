<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Tests\system\Functional\Database\FakeRecord;

// cspell:ignore handies uncompahgre

/**
 * Trait to manage data samples for test tables.
 */
trait DatabaseTestSchemaDataTrait {

  /**
   * Sets up our sample data.
   */
  protected function addSampleData(): void {

    // We need the IDs, so we can't use a multi-insert here.
    $john = $this->connection->insert('test')
      ->fields([
        'name' => 'John',
        'age' => 25,
        'job' => 'Singer',
      ])
      ->execute();

    $george = $this->connection->insert('test')
      ->fields([
        'name' => 'George',
        'age' => 27,
        'job' => 'Singer',
      ])
      ->execute();

    $this->connection->insert('test')
      ->fields([
        'name' => 'Ringo',
        'age' => 28,
        'job' => 'Drummer',
      ])
      ->execute();

    $paul = $this->connection->insert('test')
      ->fields([
        'name' => 'Paul',
        'age' => 26,
        'job' => 'Songwriter',
      ])
      ->execute();

    $this->connection->insert('test_classtype')
      ->fields([
        'classname' => FakeRecord::class,
        'name' => 'Kay',
        'age' => 26,
        'job' => 'Web Developer',
      ])
      ->execute();

    $this->connection->insert('test_people')
      ->fields([
        'name' => 'Meredith',
        'age' => 30,
        'job' => 'Speaker',
      ])
      ->execute();

    $this->connection->insert('test_task')
      ->fields(['pid', 'task', 'priority'])
      ->values([
        'pid' => $john,
        'task' => 'eat',
        'priority' => 3,
      ])
      ->values([
        'pid' => $john,
        'task' => 'sleep',
        'priority' => 4,
      ])
      ->values([
        'pid' => $john,
        'task' => 'code',
        'priority' => 1,
      ])
      ->values([
        'pid' => $george,
        'task' => 'sing',
        'priority' => 2,
      ])
      ->values([
        'pid' => $george,
        'task' => 'sleep',
        'priority' => 2,
      ])
      ->values([
        'pid' => $paul,
        'task' => 'found new band',
        'priority' => 1,
      ])
      ->values([
        'pid' => $paul,
        'task' => 'perform at superbowl',
        'priority' => 3,
      ])
      ->execute();

    $this->connection->insert('select')
      ->fields([
        'id' => 1,
        'update' => 'Update value 1',
      ])
      ->execute();

    $this->connection->insert('virtual')
      ->fields([
        'id' => 1,
        'function' => 'Function value 1',
      ])
      ->execute();

    $this->addSampleJsonData();
  }

  /**
   * Inserts the sample data of the 'json' table.
   *
   * This is separate from addSampleData(), so a test can empty the 'json'
   * table and fill it again without touching the other tables.
   */
  protected function addSampleJsonData(): void {
    $this->connection->insert('json')
      ->fields(['id', 'status', 'test_field'])
      ->values([
        'id' => 1,
        'status' => 1,
        'test_field' => '{"boolean": true, "number": 5}',
      ])
      ->values([
        'id' => 2,
        'status' => 1,
        'test_field' => '{"number": 10, "boolean": false}',
      ])
      ->values([
        'id' => 3,
        'status' => 1,
        'test_field' => '{"number": 10, "key": "some text"}',
      ])
      ->values([
        'id' => 4,
        'status' => 1,
        'test_field' => '{"boolean": true, "key": "some other text"}',
      ])
      ->values([
        'id' => 5,
        'status' => 1,
        'test_field' => '{"array": [32, 41], "float": 1.5}',
      ])
      ->values([
        'id' => 6,
        'status' => 1,
        'test_field' => '{"associative": {"mountain": "Handies"}}',
      ])
      ->values([
        'id' => 7,
        'status' => 1,
        'test_field' => NULL,
      ])
      ->values([
        'id' => 8,
        'status' => 1,
        'test_field' => '{"float": 3.14, "nested": {"inner": "deep value"}, "items": [10, 20, 30], "special": "100% off_sale"}',
      ])
      ->values([
        'id' => 9,
        'status' => 1,
        'test_field' => '{"hyphen-key": "hyphen value", "nested-obj": {"inner-key": 42}}',
      ])
      ->values([
        'id' => 10,
        'status' => 1,
        'test_field' => '{"deps": {"a": {"status": "enabled"}, "b": {"status": "disabled"}, "c": {"status": "enabled"}}, "counts": {"a": 7, "b": 8}, "revs": [{"uid": 3, "name": "editor"}, {"uid": 5, "name": "author"}]}',
      ])
      ->execute();
  }

  /**
   * Data provider of JSON condition test cases.
   *
   * The expectations describe the rows of the 'json' table that addSampleData()
   * inserts, so they apply to any query type that supports jsonCondition().
   *
   * @return array[]
   *   Test cases, each an array of a jsonpath, a value, an operator and the
   *   IDs of the sample rows the condition matches.
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

      // A value of another JSON type than the compared value matches no
      // operator. Rows 3 and 4 hold a string at $.key, so a numeric or boolean
      // comparison skips them instead of casting their value. Casting is
      // either an error or a silent 0, depending on the backend.
      'Number against string value' => ['$.key', 42, '=', []],
      'Number against string value negated' => ['$.key', 42, '<>', []],
      'Number against string value LT' => ['$.key', 42, '<', []],
      'Float against string value' => ['$.key', 1.5, '=', []],
      'Number IN against string value' => ['$.key', [42], 'IN', []],
      'Number NOT IN against string value' => ['$.key', [42], 'NOT IN', []],
      'Number BETWEEN against string value' => ['$.key', [1, 100], 'BETWEEN', []],
      'Number NOT BETWEEN against string value' => ['$.key', [1, 100], 'NOT BETWEEN', []],
      'Boolean against string value' => ['$.key', FALSE, '=', []],
      'Boolean IN against string value' => ['$.key', [FALSE], 'IN', []],
      'Boolean against number value' => ['$.number', FALSE, '=', []],
      'Boolean IN against number value' => ['$.number', [FALSE], 'IN', []],

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

      // Wildcard comparisons against values of another type. A numeric
      // comparison must skip the string elements instead of casting them.
      'Wildcard numeric value against strings' => ['$.deps.*.status', 42, '=', []],
      // A string cast to a number yields 0 on SQLite, so an unguarded
      // comparison against 0 would match the string elements of $.deps.
      'Wildcard zero against strings' => ['$.deps.*.status', 0, '=', []],
      'Wildcard numeric equals' => ['$.counts.*', 7, '=', [10]],
      'Wildcard numeric greater than' => ['$.counts.*', 7, '>', [10]],
      'Wildcard numeric no match' => ['$.counts.*', 99, '=', []],
      'Wildcard float against integers' => ['$.counts.*', 8.0, '=', [10]],
      'Wildcard text value against numbers' => ['$.counts.*', 'seven', '=', []],

      // Wildcard IN / NOT IN.
      'Wildcard IN matching one' => ['$.deps.*.status', ['enabled'], 'IN', [10]],
      'Wildcard IN matching both' => ['$.deps.*.status', ['enabled', 'disabled'], 'IN', [10]],
      'Wildcard IN no match' => ['$.deps.*.status', ['unknown'], 'IN', []],
      'Wildcard NOT IN excludes all' => ['$.deps.*.status', ['enabled', 'disabled'], 'NOT IN', []],
      'Wildcard NOT IN partial' => ['$.deps.*.status', ['enabled'], 'NOT IN', [10]],
      'Wildcard top-level IN' => ['$.*.inner', ['deep value', 'other'], 'IN', [8]],
      'Wildcard numeric IN' => ['$.counts.*', [7, 8], 'IN', [10]],
      'Wildcard numeric IN no match' => ['$.counts.*', [98, 99], 'IN', []],
      'Wildcard numeric IN against strings' => ['$.deps.*.status', [42], 'IN', []],

      // Wildcard string operators. The operators apply to every value the
      // wildcard matches, so row 10 matches when one of the three $.deps
      // members holds a status the pattern fits.
      'Wildcard CONTAINS matching one' => ['$.deps.*.status', 'dis', 'CONTAINS', [10]],
      'Wildcard CONTAINS matching all' => ['$.deps.*.status', 'able', 'CONTAINS', [10]],
      'Wildcard CONTAINS no match' => ['$.deps.*.status', 'xyz', 'CONTAINS', []],
      'Wildcard STARTS_WITH matching' => ['$.deps.*.status', 'dis', 'STARTS_WITH', [10]],
      'Wildcard STARTS_WITH no match' => ['$.deps.*.status', 'able', 'STARTS_WITH', []],
      'Wildcard ENDS_WITH matching' => ['$.deps.*.status', 'bled', 'ENDS_WITH', [10]],
      'Wildcard ENDS_WITH no match' => ['$.deps.*.status', 'dis', 'ENDS_WITH', []],
      'Wildcard top-level CONTAINS' => ['$.*.inner', 'deep', 'CONTAINS', [8]],
      // The LIKE pattern of a string operator is escaped, so % and _ compare
      // as literals under a wildcard path as well.
      'Wildcard CONTAINS percent no false positive' => ['$.deps.*.status', '%', 'CONTAINS', []],
      'Wildcard CONTAINS underscore no false positive' => ['$.deps.*.status', '_', 'CONTAINS', []],
      'Wildcard CONTAINS quotes' => ['$.deps.*.status', "' OR '1'='1", 'CONTAINS', []],

      // Wildcard BETWEEN / NOT BETWEEN. Row 10 holds 7 and 8 at $.counts.
      'Wildcard BETWEEN matching both' => ['$.counts.*', [7, 8], 'BETWEEN', [10]],
      'Wildcard BETWEEN matching one' => ['$.counts.*', [8, 9], 'BETWEEN', [10]],
      'Wildcard BETWEEN no match' => ['$.counts.*', [9, 10], 'BETWEEN', []],
      'Wildcard NOT BETWEEN matching one' => ['$.counts.*', [8, 9], 'NOT BETWEEN', [10]],
      'Wildcard NOT BETWEEN excludes all' => ['$.counts.*', [7, 8], 'NOT BETWEEN', []],
      'Wildcard float BETWEEN against integers' => ['$.counts.*', [7.5, 8.5], 'BETWEEN', [10]],
      // A value of another JSON type than the compared value is skipped, so it
      // matches neither BETWEEN nor NOT BETWEEN.
      'Wildcard BETWEEN against strings' => ['$.deps.*.status', [1, 100], 'BETWEEN', []],
      'Wildcard NOT BETWEEN against strings' => ['$.deps.*.status', [1, 100], 'NOT BETWEEN', []],

      // Array element wildcard. The [*] accessor selects the elements of an
      // array, where the .* accessor selects the members of an object. Row 8
      // holds an array of numbers at $.items, row 5 one at $.array.
      'Element wildcard equals' => ['$.items[*]', 20, '=', [8]],
      'Element wildcard first element' => ['$.items[*]', 10, '=', [8]],
      'Element wildcard no match' => ['$.items[*]', 99, '=', []],
      'Element wildcard greater than' => ['$.items[*]', 25, '>', [8]],
      'Element wildcard on other array' => ['$.array[*]', 41, '=', [5]],
      'Element wildcard IN' => ['$.items[*]', [20, 99], 'IN', [8]],
      'Element wildcard IN no match' => ['$.items[*]', [98, 99], 'IN', []],
      'Element wildcard text against numbers' => ['$.items[*]', 'twenty', '=', []],

      // Array of objects, the shape a table of rows takes in a JSON document.
      // Row 10 holds [{"uid": 3, ...}, {"uid": 5, ...}] at $.revs, so
      // $.revs[*].uid reads the uid of every element.
      'Element wildcard nested equals' => ['$.revs[*].uid', 3, '=', [10]],
      'Element wildcard nested other value' => ['$.revs[*].uid', 5, '=', [10]],
      'Element wildcard nested no match' => ['$.revs[*].uid', 4, '=', []],
      'Element wildcard nested greater than' => ['$.revs[*].uid', 4, '>', [10]],
      'Element wildcard nested text' => ['$.revs[*].name', 'editor', '=', [10]],
      'Element wildcard nested IN' => ['$.revs[*].uid', [3, 7], 'IN', [10]],
      'Element wildcard nested IN no match' => ['$.revs[*].uid', [7, 9], 'IN', []],
      'Element wildcard nested NOT IN' => ['$.revs[*].uid', [3], 'NOT IN', [10]],
      'Element wildcard nested NOT IN excludes all' => ['$.revs[*].uid', [3, 5], 'NOT IN', []],
      'Element wildcard nested text against numbers' => ['$.revs[*].uid', 'three', '=', []],
      'Element wildcard nested zero against strings' => ['$.revs[*].name', 0, '=', []],
      'Element wildcard BETWEEN' => ['$.items[*]', [15, 25], 'BETWEEN', [8]],
      'Element wildcard BETWEEN no match' => ['$.items[*]', [40, 50], 'BETWEEN', []],
      'Element wildcard NOT BETWEEN' => ['$.items[*]', [10, 25], 'NOT BETWEEN', [8]],
      'Element wildcard nested BETWEEN' => ['$.revs[*].uid', [4, 6], 'BETWEEN', [10]],
      'Element wildcard nested BETWEEN no match' => ['$.revs[*].uid', [6, 9], 'BETWEEN', []],
      'Element wildcard nested NOT BETWEEN' => ['$.revs[*].uid', [3, 4], 'NOT BETWEEN', [10]],
      'Element wildcard nested CONTAINS' => ['$.revs[*].name', 'edit', 'CONTAINS', [10]],
      'Element wildcard nested CONTAINS no match' => ['$.revs[*].name', 'reviewer', 'CONTAINS', []],
      'Element wildcard nested STARTS_WITH' => ['$.revs[*].name', 'auth', 'STARTS_WITH', [10]],
      'Element wildcard nested ENDS_WITH' => ['$.revs[*].name', 'thor', 'ENDS_WITH', [10]],
    ];
  }

  /**
   * Data provider of JSON expression test cases.
   *
   * The expectations describe the rows of the 'json' table that addSampleData()
   * inserts, so they apply to any query type that supports jsonExpression().
   *
   * @return array[]
   *   Test cases, each an array of a jsonpath, the ID of the sample row to
   *   extract from and the value the extraction writes.
   */
  public static function providerJsonExpressionPaths(): array {
    return [
      'nested' => ['$.nested.inner', 8, 'deep value'],
      'hyphenated' => ['$.hyphen-key', 9, 'hyphen value'],
      'bracket notation' => ['$["hyphen-key"]', 9, 'hyphen value'],
      'array index' => ['$.items[1]', 8, '20'],
    ];
  }

}
