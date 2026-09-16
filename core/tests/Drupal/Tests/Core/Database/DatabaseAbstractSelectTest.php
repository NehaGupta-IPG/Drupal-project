<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\InvalidQueryException;
use Drupal\Core\Database\Query\DatabaseAbstract\Condition as DatabaseAbstractCondition;
use Drupal\Core\Database\Query\DatabaseAbstract\Select as DatabaseAbstractSelect;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('Database')]
class DatabaseAbstractSelectTest extends UnitTestCase {

  // ------------------------------------------------------ from SelectComplexTest

  /**
   * Tests GROUP BY and HAVING clauses together.
   */
  public function testGroupByAndHaving(): void {
    $query = new DatabaseAbstractSelect('test_task', 't');
    $count_field = $query->addExpression('COUNT([task])', 'num');
    $task_field = $query->addColumn('t', 'task');
    $query->orderBy($count_field);
    $query->groupBy($task_field);
    $query->having('COUNT([task]) >= 2');

    dump($query);
  }

  /**
   * Tests range queries.
   *
   * The SQL clause varies with the database.
   */
  public function testRange(): void {
    $query = new DatabaseAbstractSelect('test');
    $query->addColumn('test', 'name');
    $query->addColumn('test', 'age', 'age');
    $query->range(0, 2);
    $query = $query->countQuery();

    dump($query);
  }

  /**
   * Tests whether the range property of a select clause can be undone.
   */
  public function testRangeUndo(): void {
    $query = new DatabaseAbstractSelect('test');
    $query->addColumn('test', 'name');
    $query->addColumn('test', 'age', 'age');
    $query->range(0, 2);
    $query->range();
    $query = $query->countQuery();

    dump($query);
  }

  /**
   * Tests distinct queries.
   */
  public function testDistinct(): void {
    $query = new DatabaseAbstractSelect('test_task');
    $query->addColumn('test_task', 'task');
    $query->orderBy('task');
    $query->distinct();

    dump($query);
  }

  /**
   * Tests that we can generate a count query from a built query.
   */
  public function testCountQuery(): void {
    $query = new DatabaseAbstractSelect('test');
    $name_field = $query->addColumn('test', 'name');
    $age_field = $query->addColumn('test', 'age', 'age');
    $query->orderBy('name');

    $query = $query->countQuery();

    dump($query);
  }

  /**
   * Tests having queries.
   */
  public function testHavingCountQuery(): void {
    $query = new DatabaseAbstractSelect('test')
      ->groupBy('age')
      ->having('[age] + 1 > 0');
    $query->addColumn('test', 'age');
    $query->addExpression('[age] + 1');

    dump($query);
  }

  /**
   * Tests that countQuery removes 'all_fields' statements and ordering clauses.
   */
  public function testCountQueryRemovals(): void {
    $query = new DatabaseAbstractSelect('test');
    $query->fields('test');
    $query->orderBy('name');
    $count = $query->countQuery();

    // Check that the 'all_fields' statement is handled properly.
    $tables = $query->getTables();
    $this->assertEquals(1, $tables['test']['all_fields'], 'Query correctly sets \'all_fields\' statement.');
    $tables = $count->getTables();
    $this->assertFalse(isset($tables['test']['all_fields']), 'Count query correctly unsets \'all_fields\' statement.');

    // Check that the ordering clause is handled properly.
    $orderby = $query->getOrderBy();
    // The orderby string is different for PostgreSQL.
    // @see Drupal\pgsql\Driver\Database\pgsql\Select::orderBy()
    $db_type = Database::getConnection()->databaseType();
    $this->assertEquals($db_type == 'pgsql' ? 'ASC NULLS FIRST' : 'ASC', $orderby['name'], 'Query correctly sets ordering clause.');
    $orderby = $count->getOrderBy();
    $this->assertFalse(isset($orderby['name']), 'Count query correctly unsets ordering clause.');

    // Make sure that the count query works.
    $count = $count->execute()->fetchField();

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
    $query = new DatabaseAbstractSelect('test');
    $query->fields('test', ['fail']);
    $this->assertEquals(4, $query->countQuery()->execute()->fetchField(), 'Count Query removed fields');

    $query = new DatabaseAbstractSelect('test');
    $query->addExpression('[fail]');
    $this->assertEquals(4, $query->countQuery()->execute()->fetchField(), 'Count Query removed expressions');
  }

  /**
   * Tests that we can generate a count query from a query with distinct.
   */
  public function testCountQueryDistinct(): void {
    $query = new DatabaseAbstractSelect('test_task');
    $query->addColumn('test_task', 'task');
    $query->distinct();

    $query = $query->countQuery();

    dump($query);
  }

  /**
   * Tests that we can generate a count query from a query with GROUP BY.
   */
  public function testCountQueryGroupBy(): void {
    $query = new DatabaseAbstractSelect('test_task');
    $query->addColumn('test_task', 'pid');
    $query->groupBy('pid');

    $query = $query->countQuery();

    dump($query);

    // Use a column alias as, without one, the query can succeed for the wrong
    // reason.
    $query = new DatabaseAbstractSelect('test_task');
    $query->addColumn('test_task', 'pid', 'pid_alias');
    $query->addExpression('COUNT([test_task].[task])', 'count');
    $query->groupBy('pid_alias');
    $query->orderBy('pid_alias', 'asc');

    $query = $query->countQuery();

    dump($query);
  }

  /**
   * Confirms that we can properly nest conditional clauses.
   */
  public function testNestedConditions(): void {
    // This query should translate to:
    // "SELECT job FROM {test} WHERE name = 'Paul' AND (age = 26 OR age = 27)"
    // That should find only one record. Yes it's a non-optimal way of writing
    // that query but that's not the point!
    $query = new DatabaseAbstractSelect('test');
    $query->addColumn('test', 'job');
    $query->condition('name', 'Paul');
    $query->condition((new DatabaseAbstractCondition('OR'))->condition('age', 26)->condition('age', 27));

    dump($query);
  }

  /**
   * Confirms we can join on a single table twice with a dynamic alias.
   */
  public function testJoinTwice(): void {
    $query = new DatabaseAbstractSelect('test')->fields('test');
    $alias = $query->join('test', 'test', '[test].[job] = [%alias].[job]');
    $query->addColumn($alias, 'name', 'other_name');
    $query->addColumn($alias, 'job', 'other_job');
    $query->where("[$alias].[name] <> [test].[name]");
    $crowded_job = $query->execute()->fetch();
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

    $query = Database::getConnection('replica')->select('test_task', 'tt');
    $query->addExpression('[tt].[pid] + 1', 'abc');
    $query->condition('priority', 1, '>');
    $query->condition('priority', 100, '<');

    $subquery = new DatabaseAbstractSelect('test', 'tp');
    $subquery->join('test_one_blob', 'tpb', '[tp].[id] = [tpb].[id]');
    $subquery->join('node', 'n', '[tp].[id] = [n].[nid]');
    $subquery->addTag('node_access');
    $subquery->addMetaData('account', $account);
    $subquery->addColumn('tp', 'id');
    $subquery->condition('age', 5, '>');
    $subquery->condition('age', 500, '<');

    $query->leftJoin($subquery, 'sq', '[tt].[pid] = [sq].[id]');
    $query->join('test_one_blob', 'tb3', '[tt].[pid] = [tb3].[id]');

    // Construct the query string.
    // This is the same sequence that SelectQuery::execute() goes through.
    $query->preExecute();
    $query->getArguments();
    $str = (string) $query;

    // Verify that the string only has one copy of condition placeholder 0.
    $pos = strpos($str, 'db_condition_placeholder_0', 0);
    $pos2 = strpos($str, 'db_condition_placeholder_0', $pos + 1);
    $this->assertFalse($pos2, 'Condition placeholder is not repeated.');
  }

  /**
   * Tests that rowCount() throws exception on SELECT query.
   */
  public function testSelectWithRowCount(): void {
    $query = new DatabaseAbstractSelect('test');
    $query->addColumn('test', 'name');
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
    $query = new DatabaseAbstractSelect('test_task', 't');
    $join_cond = ($this->connection->condition('AND'))->where('[t].[pid] = [p].[id]');
    $people_alias = $query->join('test', 'p', $join_cond);
    $name_field = $query->addColumn($people_alias, 'name', 'name');
    $query->addColumn('t', 'task', 'task');
    $priority_field = $query->addColumn('t', 'priority', 'priority');

    $query->orderBy($priority_field);
    $result = $query->execute();

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

    // Test a condition object that creates placeholders.
    $t1_name = 'John';
    $t2_name = 'George';
    $join_cond = ($this->connection->condition('AND'))
      ->condition('t1.name', $t1_name)
      ->condition('t2.name', $t2_name);
    $query = new DatabaseAbstractSelect('test', 't1');
    $query->innerJoin('test', 't2', $join_cond);
    $query->addColumn('t1', 'name', 't1_name');
    $query->addColumn('t2', 'name', 't2_name');

    $num_records = $query->countQuery()->execute()->fetchField();
    $this->assertEquals(1, $num_records, 'Query expected to return 1 row. Actual: ' . $num_records);
    if ($num_records == 1) {
      $record = $query->execute()->fetchObject();
      $this->assertEquals($t1_name, $record->t1_name, 'Query expected to retrieve name ' . $t1_name . ' from table t1. Actual: ' . $record->t1_name);
      $this->assertEquals($t2_name, $record->t2_name, 'Query expected to retrieve name ' . $t2_name . ' from table t2. Actual: ' . $record->t2_name);
    }
  }

}
