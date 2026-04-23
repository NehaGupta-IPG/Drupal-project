<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\config_test\Entity\ConfigQueryTest;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Config\Entity\Query\SqlQuery;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Config Entity Query functionality.
 *
 * @see \Drupal\Core\Config\Entity\Query
 */
#[Group('Entity')]
#[RunTestsInSeparateProcesses]
class ConfigEntityQueryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_test'];

  /**
   * Stores the search results for alter comparison.
   *
   * @var array
   */
  protected $queryResults;

  /**
   * The config entity storage used for testing.
   */
  protected ConfigEntityStorageInterface $entityStorage;

  /**
   * Stores all config entities created for the test.
   *
   * @var array
   */
  protected $entities;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entities = [];
    $this->entityStorage = $this->container->get('entity_type.manager')->getStorage('config_query_test');

    // These two are here to make sure that matchArray needs to go over several
    // non-matches on every levels.
    $array['level1']['level2a'] = 9;
    $array['level1a']['level2'] = 9;
    // The tests match array.level1.level2.
    $array['level1']['level2'] = 1;
    $entity = ConfigQueryTest::create([
      'label' => 'entity_1',
      'id' => '1',
      'number' => 31,
      'flag' => TRUE,
      'array' => $array,
    ]);
    $this->entities[] = $entity;
    $entity->enforceIsNew();
    $entity->save();

    $array['level1']['level2'] = 2;
    $entity = ConfigQueryTest::create([
      'label' => 'entity_2',
      'id' => '2',
      'number' => 41,
      'array' => $array,
    ]);
    $this->entities[] = $entity;
    $entity->enforceIsNew();
    $entity->save();

    $array['level1']['level2'] = 1;
    $entity = ConfigQueryTest::create([
      'label' => 'test_prefix_entity_3',
      'id' => '3',
      'number' => 59,
      'flag' => TRUE,
      'array' => $array,
    ]);
    $this->entities[] = $entity;
    $entity->enforceIsNew();
    $entity->save();

    $array['level1']['level2'] = 2;
    $entity = ConfigQueryTest::create([
      'label' => 'entity_4_test_suffix',
      'id' => '4',
      'number' => 26,
      'array' => $array,
    ]);
    $this->entities[] = $entity;
    $entity->enforceIsNew();
    $entity->save();

    $array['level1']['level2'] = 3;
    $entity = ConfigQueryTest::create([
      'label' => 'entity_5_TEST_contains_entity_5',
      'id' => '5',
      'number' => 53,
      'flag' => TRUE,
      'array' => $array,
    ]);
    $this->entities[] = $entity;
    $entity->enforceIsNew();
    $entity->save();

    $array['level1'] = [];
    $entity = ConfigQueryTest::create([
      'label' => $this->randomMachineName(),
      'id' => '6',
      'array' => $array,
    ]);
    $this->entities[] = $entity;
    $entity->enforceIsNew();
    $entity->save();

    $array['level1']['level2'] = 4;
    $entity = ConfigQueryTest::create([
      'label' => $this->randomMachineName(),
      'id' => '7',
      'number' => 70,
      'array' => $array,
    ]);
    $this->entities[] = $entity;
    $entity->enforceIsNew();
    $entity->save();
  }

  /**
   * Tests basic functionality.
   */
  public function testConfigEntityQuery(): void {
    // Verify that the query factory returns a SqlQuery instance by default.
    $this->assertInstanceOf(SqlQuery::class, $this->entityStorage->getQuery());

    // Run a test without any condition.
    $this->queryResults = $this->entityStorage->getQuery()
      ->execute();
    $this->assertResults(['1', '2', '3', '4', '5', '6', '7']);
    // No conditions, OR.
    $this->queryResults = $this->entityStorage->getQuery('OR')
      ->execute();
    $this->assertResults(['1', '2', '3', '4', '5', '6', '7']);

    // Filter by ID with equality.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', '3')
      ->execute();
    $this->assertResults(['3']);

    // Filter by label with a known prefix.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('label', 'test_prefix', 'STARTS_WITH')
      ->execute();
    $this->assertResults(['3']);

    // Filter by label with a known suffix.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('label', 'test_suffix', 'ENDS_WITH')
      ->execute();
    $this->assertResults(['4']);

    // Filter by label with a known containing word.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('label', 'test_contains', 'CONTAINS')
      ->execute();
    $this->assertResults(['5']);

    // Filter by ID with the IN operator.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', ['2', '3'], 'IN')
      ->execute();
    $this->assertResults(['2', '3']);

    // Filter by ID with the implicit IN operator.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', ['2', '3'])
      ->execute();
    $this->assertResults(['2', '3']);

    // Filter by ID with the > operator.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', '3', '>')
      ->execute();
    $this->assertResults(['4', '5', '6', '7']);

    // Filter by ID with the >= operator.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', '3', '>=')
      ->execute();
    $this->assertResults(['3', '4', '5', '6', '7']);

    // Filter by ID with the <> operator.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', '3', '<>')
      ->execute();
    $this->assertResults(['1', '2', '4', '5', '6', '7']);

    // Filter by ID with the < operator.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', '3', '<')
      ->execute();
    $this->assertResults(['1', '2']);

    // Filter by ID with the <= operator.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', '3', '<=')
      ->execute();
    $this->assertResults(['1', '2', '3']);

    // Filter by two conditions on the same field.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('label', 'test_pref', 'STARTS_WITH')
      ->condition('label', 'test_prefix', 'STARTS_WITH')
      ->execute();
    $this->assertResults(['3']);

    // Filter by two conditions on different fields. The first query matches for
    // a different ID, so the result is empty.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('label', 'test_prefix', 'STARTS_WITH')
      ->condition('id', '5')
      ->execute();
    $this->assertResults([]);

    // Filter by two different conditions on different fields. This time the
    // first condition matches on one item, but the second one does as well.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('label', 'test_prefix', 'STARTS_WITH')
      ->condition('id', '3')
      ->execute();
    $this->assertResults(['3']);

    // Filter by two different conditions, of which the first one matches for
    // every entry, the second one as well, but just the third one filters so
    // that just two are left.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', '1', '>=')
      ->condition('number', 10, '>=')
      ->condition('number', 50, '>=')
      ->execute();
    $this->assertResults(['3', '5', '7']);

    // Filter with an OR condition group.
    $this->queryResults = $this->entityStorage->getQuery('OR')
      ->condition('id', 1)
      ->condition('id', '2')
      ->execute();
    $this->assertResults(['1', '2']);

    // Simplify it with IN.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', ['1', '2'])
      ->execute();
    $this->assertResults(['1', '2']);
    // Try explicit IN.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', ['1', '2'], 'IN')
      ->execute();
    $this->assertResults(['1', '2']);
    // Try not IN.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', ['1', '2'], 'NOT IN')
      ->execute();
    $this->assertResults(['3', '4', '5', '6', '7']);

    // Filter with an OR condition group on different fields.
    $this->queryResults = $this->entityStorage->getQuery('OR')
      ->condition('id', 1)
      ->condition('number', 41)
      ->execute();
    $this->assertResults(['1', '2']);

    // Filter with an OR condition group on different fields but matching on the
    // same entity.
    $this->queryResults = $this->entityStorage->getQuery('OR')
      ->condition('id', 1)
      ->condition('number', 31)
      ->execute();
    $this->assertResults(['1']);

    // NO simple conditions, YES complex conditions, 'AND'.
    $query = $this->entityStorage->getQuery('AND');
    $and_condition_1 = $query->orConditionGroup()
      ->condition('id', '2')
      ->condition('label', $this->entities[0]->label);
    $and_condition_2 = $query->orConditionGroup()
      ->condition('id', 1)
      ->condition('label', $this->entities[3]->label);
    $this->queryResults = $query
      ->condition($and_condition_1)
      ->condition($and_condition_2)
      ->execute();
    $this->assertResults(['1']);

    // NO simple conditions, YES complex conditions, 'OR'.
    $query = $this->entityStorage->getQuery('OR');
    $and_condition_1 = $query->andConditionGroup()
      ->condition('id', 1)
      ->condition('label', $this->entities[0]->label);
    $and_condition_2 = $query->andConditionGroup()
      ->condition('id', '2')
      ->condition('label', $this->entities[1]->label);
    $this->queryResults = $query
      ->condition($and_condition_1)
      ->condition($and_condition_2)
      ->execute();
    $this->assertResults(['1', '2']);

    // YES simple conditions, YES complex conditions, 'AND'.
    $query = $this->entityStorage->getQuery('AND');
    $and_condition_1 = $query->orConditionGroup()
      ->condition('id', '2')
      ->condition('label', $this->entities[0]->label);
    $and_condition_2 = $query->orConditionGroup()
      ->condition('id', 1)
      ->condition('label', $this->entities[3]->label);
    $this->queryResults = $query
      ->condition('number', 31)
      ->condition($and_condition_1)
      ->condition($and_condition_2)
      ->execute();
    $this->assertResults(['1']);

    // YES simple conditions, YES complex conditions, 'OR'.
    $query = $this->entityStorage->getQuery('OR');
    $and_condition_1 = $query->orConditionGroup()
      ->condition('id', '2')
      ->condition('label', $this->entities[0]->label);
    $and_condition_2 = $query->orConditionGroup()
      ->condition('id', 1)
      ->condition('label', $this->entities[3]->label);
    $this->queryResults = $query
      ->condition('number', 53)
      ->condition($and_condition_1)
      ->condition($and_condition_2)
      ->execute();
    $this->assertResults(['1', '2', '4', '5']);

    // Test the exists and notExists conditions.
    $this->queryResults = $this->entityStorage->getQuery()
      ->exists('id')
      ->execute();
    $this->assertResults(['1', '2', '3', '4', '5', '6', '7']);

    $this->queryResults = $this->entityStorage->getQuery()
      ->exists('non-existent')
      ->execute();
    $this->assertResults([]);

    $this->queryResults = $this->entityStorage->getQuery()
      ->notExists('id')
      ->execute();
    $this->assertResults([]);

    $this->queryResults = $this->entityStorage->getQuery()
      ->notExists('non-existent')
      ->execute();
    $this->assertResults(['1', '2', '3', '4', '5', '6', '7']);
  }

  /**
   * Tests ID conditions.
   */
  public function testStringIdConditions(): void {
    // We need an entity with a non-numeric ID.
    $entity = ConfigQueryTest::create([
      'label' => 'entity_1',
      'id' => 'foo.bar',
    ]);
    $this->entities[] = $entity;
    $entity->enforceIsNew();
    $entity->save();

    // Test 'STARTS_WITH' condition.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', 'foo.bar', 'STARTS_WITH')
      ->execute();
    $this->assertResults(['foo.bar']);
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', 'f', 'STARTS_WITH')
      ->execute();
    $this->assertResults(['foo.bar']);
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', 'miss', 'STARTS_WITH')
      ->execute();
    $this->assertResults([]);

    // Test 'CONTAINS' condition.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', 'foo.bar', 'CONTAINS')
      ->execute();
    $this->assertResults(['foo.bar']);
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', 'oo.ba', 'CONTAINS')
      ->execute();
    $this->assertResults(['foo.bar']);
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', 'miss', 'CONTAINS')
      ->execute();
    $this->assertResults([]);

    // Test 'ENDS_WITH' condition.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', 'foo.bar', 'ENDS_WITH')
      ->execute();
    $this->assertResults(['foo.bar']);
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', 'r', 'ENDS_WITH')
      ->execute();
    $this->assertResults(['foo.bar']);
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', 'miss', 'ENDS_WITH')
      ->execute();
    $this->assertResults([]);
  }

  /**
   * Tests count query.
   */
  public function testCount(): void {
    // Test count on no conditions.
    $count = $this->entityStorage->getQuery()
      ->count()
      ->execute();
    $this->assertCount($count, $this->entities);

    // Test count on a complex query.
    $query = $this->entityStorage->getQuery('OR');
    $and_condition_1 = $query->andConditionGroup()
      ->condition('id', 1)
      ->condition('label', $this->entities[0]->label);
    $and_condition_2 = $query->andConditionGroup()
      ->condition('id', '2')
      ->condition('label', $this->entities[1]->label);
    $count = $query
      ->condition($and_condition_1)
      ->condition($and_condition_2)
      ->count()
      ->execute();
    $this->assertSame(2, $count);
  }

  /**
   * Tests sorting and range on config entity queries.
   */
  public function testSortRange(): void {
    // Sort by simple ascending/descending.
    $this->queryResults = $this->entityStorage->getQuery()
      ->sort('number', 'DESC')
      ->execute();
    $this->assertSame(['7', '3', '5', '2', '1', '4', '6'], array_values($this->queryResults));

    $this->queryResults = $this->entityStorage->getQuery()
      ->sort('number', 'ASC')
      ->execute();
    $this->assertSame(['6', '4', '1', '2', '5', '3', '7'], array_values($this->queryResults));

    // Apply some filters and sort.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', '3', '>')
      ->sort('number', 'DESC')
      ->execute();
    $this->assertSame(['7', '5', '4', '6'], array_values($this->queryResults));

    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', '3', '>')
      ->sort('number', 'ASC')
      ->execute();
    $this->assertSame(['6', '4', '5', '7'], array_values($this->queryResults));

    // Apply a pager and sort.
    $this->queryResults = $this->entityStorage->getQuery()
      ->sort('number', 'DESC')
      ->range('2', '2')
      ->execute();
    $this->assertSame(['5', '2'], array_values($this->queryResults));

    $this->queryResults = $this->entityStorage->getQuery()
      ->sort('number', 'ASC')
      ->range('2', '2')
      ->execute();
    $this->assertSame(['1', '2'], array_values($this->queryResults));

    // Add a range to a query without a start parameter.
    $this->queryResults = $this->entityStorage->getQuery()
      ->range(0, '3')
      ->sort('id', 'ASC')
      ->execute();
    $this->assertSame(['1', '2', '3'], array_values($this->queryResults));

    // Omit optional parameters for the range and sort.
    $this->queryResults = $this->entityStorage->getQuery()
      ->range()
      ->sort('id')
      ->execute();
    $this->assertSame(['1', '2', '3', '4', '5', '6', '7'], array_values($this->queryResults));

    // Explicitly pass NULL for the range and sort.
    $this->queryResults = $this->entityStorage->getQuery()
      ->range(NULL, NULL)
      ->sort('id')
      ->execute();
    $this->assertSame(['1', '2', '3', '4', '5', '6', '7'], array_values($this->queryResults));

    // Omit the optional start parameter for the range.
    $this->queryResults = $this->entityStorage->getQuery()
      ->range(NULL, 1)
      ->sort('id')
      ->execute();
    $this->assertSame(['1'], array_values($this->queryResults));

    // Omit the optional length parameter for the range.
    $this->queryResults = $this->entityStorage->getQuery()
      ->range(4)
      ->sort('id')
      ->execute();
    $this->assertSame(['5', '6', '7'], array_values($this->queryResults));

    // Request an empty range.
    $this->queryResults = $this->entityStorage->getQuery()
      ->range(0, 0)
      ->execute();
    $this->assertEmpty($this->queryResults);

    // Apply a pager with limit 4.
    $this->queryResults = $this->entityStorage->getQuery()
      ->pager('4', 0)
      ->sort('id', 'ASC')
      ->execute();
    $this->assertSame(['1', '2', '3', '4'], array_values($this->queryResults));
  }

  /**
   * Tests sorting with tableSort on config entity queries.
   */
  public function testTableSort(): void {
    $header = [
      ['data' => 'ID', 'specifier' => 'id'],
      ['data' => 'Number', 'specifier' => 'number'],
    ];

    // Sort key: id
    // Sorting with 'DESC' upper case.
    $this->queryResults = $this->entityStorage->getQuery()
      ->tableSort($header)
      ->sort('id', 'DESC')
      ->execute();
    $this->assertSame(['7', '6', '5', '4', '3', '2', '1'], array_values($this->queryResults));

    // Sorting with 'ASC' upper case.
    $this->queryResults = $this->entityStorage->getQuery()
      ->tableSort($header)
      ->sort('id', 'ASC')
      ->execute();
    $this->assertSame(['1', '2', '3', '4', '5', '6', '7'], array_values($this->queryResults));

    // Sorting with 'desc' lower case.
    $this->queryResults = $this->entityStorage->getQuery()
      ->tableSort($header)
      ->sort('id', 'desc')
      ->execute();
    $this->assertSame(['7', '6', '5', '4', '3', '2', '1'], array_values($this->queryResults));

    // Sorting with 'asc' lower case.
    $this->queryResults = $this->entityStorage->getQuery()
      ->tableSort($header)
      ->sort('id', 'asc')
      ->execute();
    $this->assertSame(['1', '2', '3', '4', '5', '6', '7'], array_values($this->queryResults));

    // Sort key: number
    // Sorting with 'DeSc' mixed upper and lower case.
    $this->queryResults = $this->entityStorage->getQuery()
      ->tableSort($header)
      ->sort('number', 'DeSc')
      ->execute();
    $this->assertSame(['7', '3', '5', '2', '1', '4', '6'], array_values($this->queryResults));

    // Sorting with 'AsC' mixed upper and lower case.
    $this->queryResults = $this->entityStorage->getQuery()
      ->tableSort($header)
      ->sort('number', 'AsC')
      ->execute();
    $this->assertSame(['6', '4', '1', '2', '5', '3', '7'], array_values($this->queryResults));

    // Sorting with 'dEsC' mixed upper and lower case.
    $this->queryResults = $this->entityStorage->getQuery()
      ->tableSort($header)
      ->sort('number', 'dEsC')
      ->execute();
    $this->assertSame(['7', '3', '5', '2', '1', '4', '6'], array_values($this->queryResults));

    // Sorting with 'aSc' mixed upper and lower case.
    $this->queryResults = $this->entityStorage->getQuery()
      ->tableSort($header)
      ->sort('number', 'aSc')
      ->execute();
    $this->assertSame(['6', '4', '1', '2', '5', '3', '7'], array_values($this->queryResults));
  }

  /**
   * Tests dotted path matching.
   */
  public function testDotted(): void {
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('array.level1.*', 1)
      ->execute();
    $this->assertResults(['1', '3']);
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('*.level1.level2', 2)
      ->execute();
    $this->assertResults(['2', '4']);
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('array.level1.*', 3)
      ->execute();
    $this->assertResults(['5']);
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('array.level1.level2', 3)
      ->execute();
    $this->assertResults(['5']);
    // Test dotted sorting.
    $this->queryResults = $this->entityStorage->getQuery()
      ->sort('array.level1.level2')
      ->execute();
    $this->assertResults(['6', '1', '3', '2', '4', '5', '7']);
    $this->queryResults = $this->entityStorage->getQuery()
      ->sort('array.level1.level2', 'DESC')
      ->execute();
    $this->assertResults(['7', '5', '2', '4', '1', '3', '6']);
    // Make sure that values on the wildcard level do not match if there are
    // sub-keys defined. This must not find anything even if entity 2 has a
    // top-level key number with value 41.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('*.level1.level2', 41)
      ->execute();
    $this->assertResults([]);
    // Make sure that "IS NULL" and "IS NOT NULL" work correctly with
    // array-valued fields/keys.
    $this->queryResults = $this->entityStorage->getQuery()
      ->exists('array.level1.level2')
      ->execute();
    $this->assertResults(['1', '2', '3', '4', '5', '7']);
    $this->queryResults = $this->entityStorage->getQuery()
      ->exists('array.level1')
      ->execute();
    $this->assertResults(['1', '2', '3', '4', '5', '6', '7']);
    $this->queryResults = $this->entityStorage->getQuery()
      ->exists('array')
      ->execute();
    $this->assertResults(['1', '2', '3', '4', '5', '6', '7']);
    $this->queryResults = $this->entityStorage->getQuery()
      ->notExists('array.level1.level2')
      ->execute();
    $this->assertResults(['6']);
    $this->queryResults = $this->entityStorage->getQuery()
      ->notExists('array.level1')
      ->execute();
    $this->assertResults([]);
    $this->queryResults = $this->entityStorage->getQuery()
      ->notExists('array')
      ->execute();
    $this->assertResults([]);
    // Make sure that "IS NULL" and "IS NOT NULL" work correctly when the dotted
    // path cannot be fully followed.
    $this->queryResults = $this->entityStorage->getQuery()
      ->exists('does.not.exist')
      ->execute();
    $this->assertResults([]);
    $this->queryResults = $this->entityStorage->getQuery()
      ->notExists('does.not.exist')
      ->execute();
    $this->assertResults(['1', '2', '3', '4', '5', '6', '7']);

    // Wildcard IN matches entities where any expanded value is in the set.
    // Entities 1, 3 have level1.* = [9, 1]; entities 2, 4 have [9, 2].
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('array.level1.*', [1, 2], 'IN')
      ->execute();
    $this->assertResults(['1', '2', '3', '4']);
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('array.level1.*', [3], 'IN')
      ->execute();
    $this->assertResults(['5']);
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('array.level1.*', [99], 'IN')
      ->execute();
    $this->assertResults([]);

    // Wildcard NOT IN: entities where any expanded value is NOT in the set.
    // Entities 1-5 have level2a=9 (not in [1,2,3,4]), so they match.
    // Entity 7 only has level2=4 (in the set), so it doesn't match.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('array.level1.*', [1, 2, 3, 4], 'NOT IN')
      ->execute();
    $this->assertResults(['1', '2', '3', '4', '5']);
  }

  /**
   * Tests case sensitivity.
   */
  public function testCaseSensitivity(): void {
    // Filter by label with a known containing case-sensitive word.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('label', 'TEST', 'CONTAINS')
      ->execute();
    $this->assertResults(['3', '4', '5']);

    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('label', 'test', 'CONTAINS')
      ->execute();
    $this->assertResults(['3', '4', '5']);
  }

  /**
   * Test the entity query alter hooks are invoked.
   *
   * @see config_test_entity_query_tag__config_query_test__config_entity_query_alter_hook_test_alter()
   */
  public function testAlterHook(): void {
    // Run a test without any condition.
    $this->queryResults = $this->entityStorage->getQuery()
      ->execute();
    $this->assertResults(['1', '2', '3', '4', '5', '6', '7']);

    // config_test alter hook removes the entity with id '7'.
    $this->queryResults = $this->entityStorage->getQuery()
      ->addTag('config_entity_query_alter_hook_test')
      ->execute();
    $this->assertResults(['1', '2', '3', '4', '5', '6']);
  }

  /**
   * Tests boolean conditions in config entity queries.
   */
  public function testBooleanConditions(): void {
    // Equality: flag = TRUE matches entities 1, 3, 5.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('flag', TRUE)
      ->execute();
    $this->assertResults(['1', '3', '5']);

    // Equality: flag = FALSE matches entities 2, 4, 6, 7.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('flag', FALSE)
      ->execute();
    $this->assertResults(['2', '4', '6', '7']);

    // IN with boolean array (via loadByProperties pattern).
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('flag', [TRUE], 'IN')
      ->execute();
    $this->assertResults(['1', '3', '5']);

    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('flag', [FALSE], 'IN')
      ->execute();
    $this->assertResults(['2', '4', '6', '7']);

    // NOT IN with boolean.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('flag', [TRUE], 'NOT IN')
      ->execute();
    $this->assertResults(['2', '4', '6', '7']);

    // loadByProperties() with a boolean value.
    $results = $this->entityStorage->loadByProperties(['flag' => TRUE]);
    $this->assertEquals(['1', '3', '5'], array_keys($results));
  }

  /**
   * Tests IN/NOT IN with an empty value array.
   */
  public function testEmptyInCondition(): void {
    // IN with empty array matches nothing.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', [], 'IN')
      ->execute();
    $this->assertResults([]);

    // NOT IN with empty array matches everything.
    $this->queryResults = $this->entityStorage->getQuery()
      ->condition('id', [], 'NOT IN')
      ->execute();
    $this->assertResults(['1', '2', '3', '4', '5', '6', '7']);

    // loadByProperties() with NULL value.
    $results = $this->entityStorage->loadByProperties(['label' => NULL]);
    $this->assertEmpty($results);
  }

  /**
   * Asserts the results as expected regardless of order.
   *
   * @param array $expected
   *   Array of expected entity IDs.
   *
   * @internal
   */
  protected function assertResults(array $expected): void {
    $expected_count = count($expected);
    $this->assertCount($expected_count, $this->queryResults);
    foreach ($expected as $value) {
      // This also tests whether $this->queryResults[$value] is even set at all.
      $this->assertSame($value, $this->queryResults[$value]);
    }
  }

}
