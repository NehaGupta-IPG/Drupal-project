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
