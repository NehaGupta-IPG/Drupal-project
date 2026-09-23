<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\Expression;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Database\RowCountException;
use Drupal\Core\Database\Query\DatabaseAbstract\Condition as DatabaseAbstractCondition;
use Drupal\Core\Database\Query\DatabaseAbstract\Order;
use Drupal\Core\Database\Query\DatabaseAbstract\Select as DatabaseAbstractSelect;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Select query builder with more complex queries.
 */
#[Group('Database')]
#[Group('DatabaseAbstractSelect')]
#[RunTestsInSeparateProcesses]
class SelectComplexTest extends DatabaseTestBase {

  use SelectPropertyResetTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'node_access_test', 'field'];

  /**
   * Tests simple JOIN statements.
   */
  public function testDefaultJoin(): void {
    $abstractQuery = DatabaseAbstractSelect::build('test_task', 't')
      ->join('test', 'p', '[t].[pid] = [p].[id]')
      ->column('p', 'name', 'name')
      ->column('t', 'task', 'task')
      ->column('t', 'priority', 'priority')
      ->orderBy('priority');

    $query = $this->connection->select('test_task', 't');
    $people_alias = $query->join('test', 'p', '[t].[pid] = [p].[id]');
    $name_field = $query->addField($people_alias, 'name', 'name');
    $query->addField('t', 'task', 'task');
    $priority_field = $query->addField('t', 'priority', 'priority');
    $query->orderBy($priority_field);

    $hydratedQuery = $this->connection->select($abstractQuery);

    $this->resetProperties($query);
    $this->resetProperties($hydratedQuery);
    $this->assertEquals($query, $hydratedQuery);

    $result = $query->execute();
    $hydratedResult = $hydratedQuery->execute();

    $num_records = 0;
    $last_priority = 0;
    // Verify that the results are returned in the correct order.
    foreach ($result as $record) {
      $num_records++;
      $this->assertGreaterThanOrEqual($last_priority, $record->$priority_field);
      $this->assertNotSame('Ringo', $record->$name_field, 'Person without a task not selected.');
      $last_priority = $record->$priority_field;
    }
    $this->assertEquals(7, $num_records, 'Returned the correct number of rows.');

    $num_records = 0;
    $last_priority = 0;
    // Verify that the results are returned in the correct order.
    foreach ($hydratedResult as $record) {
      $num_records++;
      $this->assertGreaterThanOrEqual($last_priority, $record->priority);
      $this->assertNotSame('Ringo', $record->name, 'Person without a task not selected.');
      $last_priority = $record->priority;
    }
    $this->assertEquals(7, $num_records, 'Returned the correct number of rows.');
  }

  /**
   * Tests LEFT OUTER joins.
   */
  public function testLeftOuterJoin(): void {
    $abstractQuery = DatabaseAbstractSelect::build('test', 'p')
      ->leftJoin('test_task', 't', '[t].[pid] = [p].[id]')
      ->column('p', 'name', 'name')
      ->column('t', 'task', 'task')
      ->column('t', 'priority', 'priority')
      ->orderBy('name');

    $query = $this->connection->select('test', 'p');
    $people_alias = $query->leftJoin('test_task', 't', '[t].[pid] = [p].[id]');
    $name_field = $query->addField('p', 'name', 'name');
    $query->addField($people_alias, 'task', 'task');
    $query->addField($people_alias, 'priority', 'priority');
    $query->orderBy($name_field);

    $hydratedQuery = $this->connection->select($abstractQuery);

    $this->resetProperties($query);
    $this->resetProperties($hydratedQuery);
    $this->assertEquals($query, $hydratedQuery);

    $result = $query->execute();
    $hydratedResult = $hydratedQuery->execute();

    $num_records = 0;
    $last_name = '0';
    // Verify that the results are returned in the correct order.
    foreach ($result as $record) {
      $num_records++;
      $this->assertGreaterThanOrEqual(0, strcmp($record->$name_field, $last_name));
    }
    $this->assertEquals(8, $num_records, 'Returned the correct number of rows.');

    $num_records = 0;
    $last_name = '0';
    // Verify that the results are returned in the correct order.
    foreach ($hydratedResult as $record) {
      $num_records++;
      $this->assertGreaterThanOrEqual(0, strcmp($record->$name_field, $last_name));
    }
    $this->assertEquals(8, $num_records, 'Returned the correct number of rows.');
  }

  /**
   * Tests GROUP BY clauses.
   */
  public function testGroupBy(): void {
    $abstractQuery = DatabaseAbstractSelect::build('test_task', 't')
      ->expression('COUNT([task])', 'num')
      ->column('t', 'task')
      ->orderBy('num')
      ->groupBy('task');

    $query = $this->connection->select('test_task', 't');
    $count_field = $query->addExpression('COUNT([task])', 'num');
    $task_field = $query->addField('t', 'task');
    $query->orderBy($count_field);
    $query->groupBy($task_field);

    $hydratedQuery = $this->connection->select($abstractQuery);

    $this->resetProperties($query);
    $this->resetProperties($hydratedQuery);
    $this->assertEquals($query, $hydratedQuery);

    $this->assertMatchesRegularExpression("/ORDER BY .*[^\w\s]num[^\w\s]/", (string) $query);
    $this->assertMatchesRegularExpression("/GROUP BY .*[^\w\s]task[^\w\s]/", (string) $query);
    $this->assertMatchesRegularExpression("/ORDER BY .*[^\w\s]num[^\w\s]/", (string) $hydratedQuery);
    $this->assertMatchesRegularExpression("/GROUP BY .*[^\w\s]task[^\w\s]/", (string) $hydratedQuery);

    $result = $query->execute();
    $hydratedResult = $hydratedQuery->execute();

    $num_records = 0;
    $last_count = 0;
    $records = [];
    // Verify that the results are returned in the correct order.
    foreach ($result as $record) {
      $num_records++;
      $this->assertGreaterThanOrEqual($last_count, $record->$count_field);
      $last_count = $record->$count_field;
      $records[$record->$task_field] = $record->$count_field;
    }

    $hydratedNumRecords = 0;
    $last_count = 0;
    $hydratedRecords = [];
    // Verify that the results are returned in the correct order.
    foreach ($hydratedResult as $record) {
      $hydratedNumRecords++;
      $this->assertGreaterThanOrEqual($last_count, $record->$count_field);
      $last_count = $record->$count_field;
      $hydratedRecords[$record->$task_field] = $record->$count_field;
    }

    $correct_results = [
      'eat' => 1,
      'sleep' => 2,
      'code' => 1,
      'found new band' => 1,
      'perform at superbowl' => 1,
    ];

    foreach ($correct_results as $task => $count) {
      $this->assertEquals($count, $records[$task], "Correct number of '$task' records found.");
      $this->assertEquals($count, $hydratedRecords[$task], "Correct number of '$task' records found.");
    }

    $this->assertEquals(6, $num_records, 'Returned the correct number of total rows.');
    $this->assertEquals(6, $hydratedNumRecords, 'Returned the correct number of total rows.');
  }

  /**
   * Tests GROUP BY and HAVING clauses together.
   */
  public function testGroupByAndHaving(): void {
    $abstractQuery = DatabaseAbstractSelect::build('test_task', 't')
      ->expression('COUNT([task])', 'num')
      ->column('t', 'task')
      ->orderBy('num')
      ->groupBy('task')
      ->having(new Expression('COUNT([task]) >= 2'));

    $query = $this->connection->select('test_task', 't');
    $count_field = $query->addExpression('COUNT([task])', 'num');
    $task_field = $query->addField('t', 'task');
    $query->orderBy($count_field);
    $query->groupBy($task_field);
    $query->having('COUNT([task]) >= 2');

    $hydratedQuery = $this->connection->select($abstractQuery);

    $this->resetProperties($query);
    $this->resetProperties($hydratedQuery);
    $this->assertEquals($query, $hydratedQuery);

    $result = $query->execute();
    $hydratedResult = $hydratedQuery->execute();

    $num_records = 0;
    $last_count = 0;
    $records = [];
    // Verify that the results are returned in the correct order.
    foreach ($result as $record) {
      $num_records++;
      $this->assertGreaterThanOrEqual(2, $record->$count_field);
      $this->assertGreaterThanOrEqual($last_count, $record->$count_field);
      $last_count = $record->$count_field;
      $records[$record->$task_field] = $record->$count_field;
    }

    $hydratedNumRecords = 0;
    $last_count = 0;
    $hydratedRecords = [];
    // Verify that the results are returned in the correct order.
    foreach ($hydratedResult as $record) {
      $hydratedNumRecords++;
      $this->assertGreaterThanOrEqual(2, $record->$count_field);
      $this->assertGreaterThanOrEqual($last_count, $record->$count_field);
      $last_count = $record->$count_field;
      $hydratedRecords[$record->$task_field] = $record->$count_field;
    }

    $correct_results = [
      'sleep' => 2,
    ];

    foreach ($correct_results as $task => $count) {
      $this->assertEquals($count, $records[$task], "Correct number of '$task' records found.");
      $this->assertEquals($count, $hydratedRecords[$task], "Correct number of '$task' records found.");
    }

    $this->assertEquals(1, $num_records, 'Returned the correct number of total rows.');
    $this->assertEquals(1, $hydratedNumRecords, 'Returned the correct number of total rows.');
  }

  /**
   * Tests range queries.
   *
   * The SQL clause varies with the database.
   */
  public function testRange(): void {
    $abstractQuery = DatabaseAbstractSelect::build('test')
      ->column('test', 'name')
      ->column('test', 'age', 'age')
      ->range(0, 2)
      ->countQuery();

    $query = $this->connection->select('test');
    $query->addField('test', 'name');
    $query->addField('test', 'age', 'age');
    $query->range(0, 2);
    $query = $query->countQuery();

    $hydratedQuery = $this->connection->select($abstractQuery);

    $this->resetProperties($query);
    $this->resetProperties($hydratedQuery);
    $this->assertEquals($query, $hydratedQuery);

    $result = $query->execute()->fetchField();
    $hydratedResult = $hydratedQuery->execute()->fetchField();

    $this->assertEquals($result, $hydratedResult);

    $this->assertEquals(2, $result, 'Returned the correct number of rows.');
  }

  /**
   * Tests whether the range property of a select clause can be undone.
   */
  public function testRangeUndo(): void {
    $abstractQuery = DatabaseAbstractSelect::build('test')
      ->column('test', 'name')
      ->column('test', 'age', 'age')
      ->range(0, 2)
      ->range()
      ->countQuery();

    $query = $this->connection->select('test');
    $query->addField('test', 'name');
    $query->addField('test', 'age', 'age');
    $query->range(0, 2);
    $query->range();
    $query = $query->countQuery();

    $hydratedQuery = $this->connection->select($abstractQuery);

    $this->resetProperties($query);
    $this->resetProperties($hydratedQuery);
    $this->assertEquals($query, $hydratedQuery);

    $result = $query->execute()->fetchField();
    $hydratedResult = $hydratedQuery->execute()->fetchField();

    $this->assertEquals($result, $hydratedResult);

    $this->assertEquals(4, $result, 'Returned the correct number of rows.');
  }

  /**
   * Tests distinct queries.
   */
  public function testDistinct(): void {
    $abstractQuery = DatabaseAbstractSelect::build('test_task')
      ->column('test_task', 'task')
      ->orderBy('task')
      ->distinct();

    $query = $this->connection->select('test_task');
    $query->addField('test_task', 'task');
    $query->orderBy('task');
    $query->distinct();

    $hydratedQuery = $this->connection->select($abstractQuery);

    $this->resetProperties($query);
    $this->resetProperties($hydratedQuery);
    $this->assertEquals($query, $hydratedQuery);

    $result = $query->execute()->fetchAll(FetchAs::Column);
    $hydratedResult = $hydratedQuery->execute()->fetchAll(FetchAs::Column);

    $this->assertEquals($result, $hydratedResult);

    $expected_result = ['code', 'eat', 'found new band', 'perform at superbowl', 'sing', 'sleep'];
    $this->assertEquals($expected_result, $result, 'Returned the correct result.');
  }

  /**
   * Tests that we can generate a count query from a built query.
   */
  public function testCountQuery(): void {
    $abstractQuery = DatabaseAbstractSelect::build('test')
      ->column('test', 'name')
      ->column('test', 'age', 'age')
      ->orderBy('name');
    $abstractCountQuery = $abstractQuery->countQuery();

    $query = $this->connection->select('test');
    $name_field = $query->addField('test', 'name');
    $age_field = $query->addField('test', 'age', 'age');
    $query->orderBy('name');
    $query_count = $query->countQuery();

    $hydratedCountQuery = $this->connection->select($abstractCountQuery);

    $this->resetProperties($query_count);
    $this->resetProperties($hydratedCountQuery);
    $this->assertEquals($query_count, $hydratedCountQuery);

    $count = $query_count->execute()->fetchField();
    $hydratedCount = $hydratedCountQuery->execute()->fetchField();

    $this->assertEquals($count, $hydratedCount);

    $this->assertEquals(4, $count, 'Counted the correct number of records.');

    // Now make sure we didn't break the original query!  We should still have
    // all of the fields we asked for.
    $record = $query->execute()->fetch();
    $hydratedRecord = $this->connection->select($abstractQuery)->execute()->fetch();
    $this->assertEquals('George', $record->{$name_field}, 'Correct data retrieved.');
    $this->assertEquals(27, $record->{$age_field}, 'Correct data retrieved.');
    $this->assertEquals($record, $hydratedRecord);
  }

  /**
   * Tests having queries.
   */
  public function testHavingCountQuery(): void {
    $abstractQuery = DatabaseAbstractSelect::build('test')
      ->groupBy('age')
      ->having(new Expression('[age] + 1 > 0'))
      ->column('test', 'age')
      ->expression('[age] + 1');

    $query = $this->connection->select('test')
      ->extend(PagerSelectExtender::class)
      ->groupBy('age')
      ->having('[age] + 1 > 0');
    $query->addField('test', 'age');
    $query->addExpression('[age] + 1');

    $hydratedQuery = $this->connection->select($abstractQuery);
    $hydratedQuery = $hydratedQuery->extend(PagerSelectExtender::class);
    $this->assertInstanceOf(SelectInterface::class, $hydratedQuery);

    $this->resetProperties($query);
    $this->resetProperties($hydratedQuery);
    $this->assertEquals($query, $hydratedQuery);

    $count = count($query->execute()->fetchCol());
    $hydratedCount = count($hydratedQuery->execute()->fetchCol());

    $this->assertEquals($count, $hydratedCount);

    $this->assertEquals(4, $count, 'Counted the correct number of records.');
  }

  /**
   * Tests that countQuery removes 'all_fields' statements and ordering clauses.
   */
  public function testCountQueryRemovals(): void {
    $abstractQuery = DatabaseAbstractSelect::build('test')
      ->columns('test')
      ->orderBy('name');
    $abstractCountQuery = $abstractQuery->countQuery();

    $query = $this->connection->select('test');
    $query->fields('test');
    $query->orderBy('name');
    $count = $query->countQuery();

    $hydratedQuery = $this->connection->select($abstractQuery);
    $hydratedCountQuery = $this->connection->select($abstractCountQuery);

    // Check that the 'all_fields' statement is handled properly.
    $tables = $query->getTables();
    $this->assertEquals(1, $tables['test']['all_fields'], 'Query correctly sets \'all_fields\' statement.');
    $tables = $count->getTables();
    $this->assertFalse(isset($tables['test']['all_fields']), 'Count query correctly unsets \'all_fields\' statement.');
    $tables = $hydratedQuery->getTables();
    $this->assertEquals(1, $tables['test']['all_fields'], 'Query correctly sets \'all_fields\' statement.');
    $tables = $hydratedCountQuery->getTables();
    $this->assertFalse(isset($tables['test']['all_fields']), 'Count query correctly unsets \'all_fields\' statement.');

    // Check that the ordering clause is handled properly.
    $orderby = $query->getOrderBy();
    // The orderby string is different for PostgreSQL.
    // @see Drupal\pgsql\Driver\Database\pgsql\Select::orderBy()
    $db_type = Database::getConnection()->databaseType();
    $this->assertEquals($db_type == 'pgsql' ? 'ASC NULLS FIRST' : 'ASC', $orderby['name'], 'Query correctly sets ordering clause.');
    $orderby = $count->getOrderBy();
    $this->assertFalse(isset($orderby['name']), 'Count query correctly unsets ordering clause.');

    // Check that the ordering clause is handled properly.
    $orderby = $hydratedQuery->getOrderBy();
    // The orderby string is different for PostgreSQL.
    // @see Drupal\pgsql\Driver\Database\pgsql\Select::orderBy()
    $db_type = Database::getConnection()->databaseType();
    $this->assertEquals($db_type == 'pgsql' ? 'ASC NULLS FIRST' : 'ASC', $orderby['name']);
    $orderby = $hydratedCountQuery->getOrderBy();
    $this->assertFalse(isset($orderby['name']));

    // Make sure that the count query works.
    $count = $count->execute()->fetchField();
    $hydratedCount = $hydratedCountQuery->execute()->fetchField();

    $this->assertEquals($count, $hydratedCount);

    $this->assertEquals(4, $count, 'Counted the correct number of records.');
  }

  /**
   * Tests that countQuery properly removes fields and expressions.
   */
  public function testCountQueryFieldRemovals(): void {
    // countQuery should remove all fields and expressions, so this can be
    // tested by adding a non-existent field and expression: if it ends
    // up in the query, an error will be thrown. If not, it will return the
    // number of records, which in this case happens to be 4 (there are four
    // records in the {test} table).
    $query = $this->connection->select('test');
    $query->fields('test', ['fail']);
    $this->assertEquals(4, $query->countQuery()->execute()->fetchField(), 'Count Query removed fields');

    $query = $this->connection->select('test');
    $query->addExpression('[fail]');
    $this->assertEquals(4, $query->countQuery()->execute()->fetchField(), 'Count Query removed expressions');

    $query = DatabaseAbstractSelect::build('test')
      ->columns('test', ['fail'])
      ->countQuery();
    $this->assertEquals(4, $this->connection->select($query)->execute()->fetchField());

    $query = DatabaseAbstractSelect::build('test')
      ->expression('[fail]')
      ->countQuery();
    $this->assertEquals(4, $this->connection->select($query)->execute()->fetchField());
  }

  /**
   * Tests that we can generate a count query from a query with distinct.
   */
  public function testCountQueryDistinct(): void {
    $query = $this->connection->select('test_task');
    $query->addField('test_task', 'task');
    $query->distinct();

    $count = $query->countQuery()->execute()->fetchField();

    $this->assertEquals(6, $count, 'Counted the correct number of records.');

    $query = DatabaseAbstractSelect::build('test_task')
      ->column('test_task', 'task')
      ->distinct()
      ->countQuery();

    $count = $this->connection->select($query)->execute()->fetchField();

    $this->assertEquals(6, $count, 'Counted the correct number of records.');
  }

  /**
   * Tests that we can generate a count query from a query with GROUP BY.
   */
  public function testCountQueryGroupBy(): void {
    $query = $this->connection->select('test_task');
    $query->addField('test_task', 'pid');
    $query->groupBy('pid');

    $count = $query->countQuery()->execute()->fetchField();

    $this->assertEquals(3, $count, 'Counted the correct number of records.');

    $query = DatabaseAbstractSelect::build('test_task')
      ->column('test_task', 'pid')
      ->groupBy('pid')
      ->countQuery();

    $count = $this->connection->select($query)->execute()->fetchField();

    $this->assertEquals(3, $count);

    // Use a column alias as, without one, the query can succeed for the wrong
    // reason.
    $abstractQuery = DatabaseAbstractSelect::build('test_task')
      ->column('test_task', 'pid', 'pid_alias')
      ->expression('COUNT([test_task].[task])', 'count')
      ->groupBy('pid_alias')
      ->orderBy('pid_alias', Order::Asc)
      ->countQuery();

    $query = $this->connection->select('test_task');
    $query->addField('test_task', 'pid', 'pid_alias');
    $query->addExpression('COUNT([test_task].[task])', 'count');
    $query->groupBy('pid_alias');
    $query->orderBy('pid_alias', 'asc');
    $count_query = $query->countQuery();

    $hydratedCountQuery = $this->connection->select($abstractQuery);

    $this->resetProperties($count_query);
    $this->resetProperties($hydratedCountQuery);
    $this->assertEquals($count_query, $hydratedCountQuery);

    $count = $count_query->execute()->fetchField();
    $hydratedCount = $hydratedCountQuery->execute()->fetchField();

    $this->assertEquals($count, $hydratedCount);

    $this->assertEquals(3, $count, 'Counted the correct number of records.');
  }

  /**
   * Confirms that we can properly nest conditional clauses.
   */
  public function testNestedConditions(): void {
    // This query should translate to:
    // "SELECT job FROM {test} WHERE name = 'Paul' AND (age = 26 OR age = 27)"
    // That should find only one record. Yes it's a non-optimal way of writing
    // that query but that's not the point!
    $abstractQuery = DatabaseAbstractSelect::build('test')
      ->column('test', 'job')
      ->condition('name', '=', 'Paul')
      ->condition(DatabaseAbstractCondition::or()
        ->condition('age', '=', 26)
        ->condition('age', '=', 27)
      );

    $query = $this->connection->select('test');
    $query->addField('test', 'job');
    $query->condition('name', 'Paul');
    $query->condition(($this->connection->condition('OR'))->condition('age', 26)->condition('age', 27));

    $hydratedQuery = $this->connection->select($abstractQuery);

    $this->resetProperties($query);
    $this->resetProperties($hydratedQuery);
    $this->assertEquals($query, $hydratedQuery);

    $job = $query->execute()->fetchField();
    $hydratedJob = $hydratedQuery->execute()->fetchField();

    $this->assertEquals($job, $hydratedJob);

    $this->assertEquals('Songwriter', $job, 'Correct data retrieved.');
  }

  /**
   * Confirms we can join on a single table twice with a dynamic alias.
   */
  public function testJoinTwice(): void {
    $abstractQuery = (DatabaseAbstractSelect::build('test'))
      ->columns('test')
      ->join('test', 'test_2', '[test].[job] = [%alias].[job]')
      ->column('test_2', 'name', 'other_name')
      ->column('test_2', 'job', 'other_job')
      ->where(new Expression("[test_2].[name] <> [test].[name]"));

    $query = $this->connection->select('test')->fields('test');
    $alias = $query->join('test', 'test', '[test].[job] = [%alias].[job]');
    $query->addField($alias, 'name', 'other_name');
    $query->addField($alias, 'job', 'other_job');
    $query->where("[$alias].[name] <> [test].[name]");

    $hydratedQuery = $this->connection->select($abstractQuery);

    $this->resetProperties($query);
    $this->resetProperties($hydratedQuery);
    $this->assertEquals($query, $hydratedQuery);

    $crowded_job = $query->execute()->fetch();
    $hydratedCrowdedJob = $hydratedQuery->execute()->fetch();

    $this->assertEquals($crowded_job, $hydratedCrowdedJob);

    $this->assertEquals($crowded_job->other_job, $crowded_job->job, 'Correctly joined same table twice.');
    $this->assertNotEquals($crowded_job->other_name, $crowded_job->name, 'Correctly joined same table twice.');
  }

  /**
   * Tests that we can join on a query.
   */
  public function testJoinSubquery(): void {
    $account = User::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
    ]);

    $abstractQuery = DatabaseAbstractSelect::build('test_task', 'tt')
      ->expression('[tt].[pid] + 1', 'abc')
      ->condition('priority', '>', 1)
      ->condition('priority', '<', 100)
      ->join('test_one_blob', 'tb3', '[tt].[pid] = [tb3].[id]')
      ->leftJoin(
        DatabaseAbstractSelect::build('test', 'tp')
          ->join('test_one_blob', 'tpb', '[tp].[id] = [tpb].[id]')
          ->join('node', 'n', '[tp].[id] = [n].[nid]')
          ->addTag('node_access')
          ->addMetaData('account', $account)
          ->column('tp', 'id')
          ->condition('age', '>', 5)
          ->condition('age', '<', 500),
        'sq',
        '[tt].[pid] = [sq].[id]',
      );

    $query = Database::getConnection('replica')->select('test_task', 'tt');
    $query->addExpression('[tt].[pid] + 1', 'abc');
    $query->condition('priority', 1, '>');
    $query->condition('priority', 100, '<');
    $subquery = $this->connection->select('test', 'tp');
    $subquery->join('test_one_blob', 'tpb', '[tp].[id] = [tpb].[id]');
    $subquery->join('node', 'n', '[tp].[id] = [n].[nid]');
    $subquery->addTag('node_access');
    $subquery->addMetaData('account', $account);
    $subquery->addField('tp', 'id');
    $subquery->condition('age', 5, '>');
    $subquery->condition('age', 500, '<');
    $query->leftJoin($subquery, 'sq', '[tt].[pid] = [sq].[id]');
    $query->join('test_one_blob', 'tb3', '[tt].[pid] = [tb3].[id]');

    $hydratedQuery = $this->connection->select($abstractQuery);

    $this->resetProperties($query);
    $this->resetProperties($hydratedQuery);
    $this->assertEquals($query, $hydratedQuery);

    // Construct the query string.
    // This is the same sequence that SelectQuery::execute() goes through.
    $query->preExecute();
    $query->getArguments();
    $str = (string) $query;

    // Verify that the string only has one copy of condition placeholder 0.
    $pos = strpos($str, 'db_condition_placeholder_0', 0);
    $pos2 = strpos($str, 'db_condition_placeholder_0', $pos + 1);
    $this->assertFalse($pos2, 'Condition placeholder is not repeated.');

    // Construct the query string.
    // This is the same sequence that SelectQuery::execute() goes through.
    $hydratedQuery->preExecute();
    $hydratedQuery->getArguments();
    $str = (string) $hydratedQuery;

    // Verify that the string only has one copy of condition placeholder 0.
    $pos = strpos($str, 'db_condition_placeholder_0', 0);
    $pos2 = strpos($str, 'db_condition_placeholder_0', $pos + 1);
    $this->assertFalse($pos2);
  }

  /**
   * Tests that rowCount() throws exception on SELECT query.
   */
  public function testSelectWithRowCount(): void {
    $query = $this->connection->select('test');
    $query->addField('test', 'name');
    $result = $query->execute();
    try {
      $result->rowCount();
      $exception = FALSE;
    }
    catch (RowCountException) {
      $exception = TRUE;
    }
    $this->assertTrue($exception, 'Exception was thrown');
  }

  /**
   * Tests that join conditions can use Condition objects.
   */
  public function testJoinConditionObject(): void {
    // Same test as testDefaultJoin, but with a Condition object.
    $abstractQuery = DatabaseAbstractSelect::build('test_task', 't')
      ->join('test', 'p', new Expression('[t].[pid] = [p].[id]'))
      ->column('p', 'name', 'name')
      ->column('t', 'task', 'task')
      ->column('t', 'priority', 'priority')
      ->orderBy('priority');

    $query = $this->connection->select('test_task', 't');
    $join_cond = ($this->connection->condition('AND'))->where('[t].[pid] = [p].[id]');
    $people_alias = $query->join('test', 'p', $join_cond);
    $name_field = $query->addField($people_alias, 'name', 'name');
    $query->addField('t', 'task', 'task');
    $priority_field = $query->addField('t', 'priority', 'priority');
    $query->orderBy($priority_field);

    $hydratedQuery = $this->connection->select($abstractQuery);

    $this->resetProperties($query);
    $this->resetProperties($hydratedQuery);
    $this->assertEquals($query, $hydratedQuery);

    $result = $query->execute();
    $hydratedResult = $hydratedQuery->execute();

    $num_records = 0;
    $last_priority = 0;
    foreach ($result as $record) {
      $num_records++;
      // Verify that the results are returned in the correct order.
      $this->assertGreaterThanOrEqual($last_priority, $record->$priority_field);
      $this->assertNotSame('Ringo', $record->$name_field, 'Person without a task not selected.');
      $last_priority = $record->$priority_field;
    }
    $this->assertEquals(7, $num_records, 'Returned the correct number of rows.');

    $num_records = 0;
    $last_priority = 0;
    foreach ($hydratedResult as $record) {
      $num_records++;
      // Verify that the results are returned in the correct order.
      $this->assertGreaterThanOrEqual($last_priority, $record->priority);
      $this->assertNotSame('Ringo', $record->name);
      $last_priority = $record->priority;
    }
    $this->assertEquals(7, $num_records);

    // Test a condition object that creates placeholders.
    $t1_name = 'John';
    $t2_name = 'George';

    $abstractQuery = DatabaseAbstractSelect::build('test', 't1')
      ->innerJoin('test', 't2', (DatabaseAbstractCondition::and()
        ->condition('t1.name', '=', $t1_name)
        ->condition('t2.name', '=', $t2_name)
      ))
      ->column('t1', 'name', 't1_name')
      ->column('t2', 'name', 't2_name');
    $abstractCountQuery = $abstractQuery->countQuery();

    $join_cond = ($this->connection->condition('AND'))
      ->condition('t1.name', $t1_name)
      ->condition('t2.name', $t2_name);
    $query = $this->connection->select('test', 't1');
    $query->innerJoin('test', 't2', $join_cond);
    $query->addField('t1', 'name', 't1_name');
    $query->addField('t2', 'name', 't2_name');
    $countQuery = $query->countQuery();

    $hydratedCountQuery = $this->connection->select($abstractCountQuery);

    $this->resetProperties($countQuery);
    $this->resetProperties($hydratedCountQuery);
    $this->assertEquals($countQuery, $hydratedCountQuery);

    $num_records = $countQuery->execute()->fetchField();
    $hydratedNumRecords = $hydratedCountQuery->execute()->fetchField();

    $this->assertEquals($num_records, $hydratedNumRecords);

    $this->assertEquals(1, $num_records, 'Query expected to return 1 row. Actual: ' . $num_records);
    if ($num_records == 1) {
      $record = $query->execute()->fetchObject();
      $hydratedRecord = $this->connection->select($abstractQuery)->execute()->fetchObject();
      $this->assertEquals($record, $hydratedRecord);
      $this->assertEquals($t1_name, $record->t1_name, 'Query expected to retrieve name ' . $t1_name . ' from table t1. Actual: ' . $record->t1_name);
      $this->assertEquals($t2_name, $record->t2_name, 'Query expected to retrieve name ' . $t2_name . ' from table t2. Actual: ' . $record->t2_name);
    }
  }

}
