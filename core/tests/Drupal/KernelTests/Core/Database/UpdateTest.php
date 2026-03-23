<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the update query builder.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class UpdateTest extends DatabaseTestBase {

  /**
   * Confirms that we can update a single record successfully.
   */
  public function testSimpleUpdate(): void {
    $this->connection->update('test')
      ->fields(['name' => 'Tiffany'])
      ->condition('id', 1)
      ->execute();

    $saved_name = $this->connection->query('SELECT [name] FROM {test} WHERE [id] = :id', [':id' => 1])->fetchField();
    $this->assertSame('Tiffany', $saved_name, 'Updated name successfully.');
  }

  /**
   * Confirms updating to NULL.
   */
  public function testSimpleNullUpdate(): void {
    $this->ensureSampleDataNull();
    $this->connection->update('test_null')
      ->fields(['age' => NULL])
      ->condition('name', 'Kermit')
      ->execute();

    $saved_age = $this->connection->query('SELECT [age] FROM {test_null} WHERE [name] = :name', [':name' => 'Kermit'])->fetchField();
    $this->assertNull($saved_age, 'Updated name successfully.');
  }

  /**
   * Confirms that we can update multiple records successfully.
   */
  public function testMultiUpdate(): void {
    $this->connection->update('test')
      ->fields(['job' => 'Musician'])
      ->condition('job', 'Singer')
      ->execute();

    $num_matches = $this->connection->query('SELECT COUNT(*) FROM {test} WHERE [job] = :job', [':job' => 'Musician'])->fetchField();
    $this->assertSame('2', $num_matches, 'Updated fields successfully.');
  }

  /**
   * Confirms that we can update multiple records with a non-equality condition.
   */
  public function testMultiGTUpdate(): void {
    $this->connection->update('test')
      ->fields(['job' => 'Musician'])
      ->condition('age', 26, '>')
      ->execute();

    $num_matches = $this->connection->query('SELECT COUNT(*) FROM {test} WHERE [job] = :job', [':job' => 'Musician'])->fetchField();
    $this->assertSame('2', $num_matches, 'Updated fields successfully.');
  }

  /**
   * Confirms that we can update multiple records with a where call.
   */
  public function testWhereUpdate(): void {
    $this->connection->update('test')
      ->fields(['job' => 'Musician'])
      ->where('[age] > :age', [':age' => 26])
      ->execute();

    $num_matches = $this->connection->query('SELECT COUNT(*) FROM {test} WHERE [job] = :job', [':job' => 'Musician'])->fetchField();
    $this->assertSame('2', $num_matches, 'Updated fields successfully.');
  }

  /**
   * Confirms that we can stack condition and where calls.
   */
  public function testWhereAndConditionUpdate(): void {
    $this->connection->update('test')
      ->fields(['job' => 'Musician'])
      ->where('[age] > :age', [':age' => 26])
      ->condition('name', 'Ringo')
      ->execute();

    $num_matches = $this->connection->query('SELECT COUNT(*) FROM {test} WHERE [job] = :job', [':job' => 'Musician'])->fetchField();
    $this->assertSame('1', $num_matches, 'Updated fields successfully.');
  }

  /**
   * Tests updating with expressions.
   */
  public function testExpressionUpdate(): void {
    // Ensure that expressions are handled properly. This should set every
    // record's age to a square of itself.
    $this->connection->update('test')
      ->expression('age', '[age] * [age]')
      ->execute();

    $num_matches = $this->connection->query('SELECT COUNT(*) FROM {test} WHERE [age] > :age', [':age' => '100'])->fetchField();
    $this->assertSame('4', $num_matches, '4 rows should have been updated, but they were not');
    $saved_name = $this->connection->query('SELECT [name] FROM {test} WHERE [age] = :age', [':age' => pow(26, 2)])->fetchField();
    $this->assertSame('Paul', $saved_name, 'Successfully updated values using an algebraic expression.');
  }

  /**
   * Tests return value on update.
   */
  public function testUpdateAffectedRows(): void {
    // At 5am in the morning, all band members but those with a priority 1 task
    // are sleeping. So we set their tasks to 'sleep'. 5 records match the
    // condition and therefore are affected by the query, even though two of
    // them actually don't have to be changed because their value was already
    // 'sleep'.
    $num_matches = $this->connection->query('SELECT COUNT(*) FROM {test_task} WHERE [priority] <> :priority', [
      ':priority' => 1,
    ])->fetchField();
    $this->assertSame('5', $num_matches, '5 rows should have been matched for update');

    $pre_matches = $this->connection->query('SELECT COUNT(*) FROM {test_task} WHERE [priority] <> :priority AND [task] = :task', [
      ':priority' => 1,
      ':task' => 'sleep',
    ])->fetchField();
    $this->assertSame('2', $pre_matches, '2 rows should have been already at sleep');

    $this->connection->update('test_task')
      ->condition('priority', 1, '<>')
      ->fields(['task' => 'sleep'])
      ->execute();

    $post_matches = $this->connection->query('SELECT COUNT(*) FROM {test_task} WHERE [priority] <> :priority AND [task] = :task', [
      ':priority' => 1,
      ':task' => 'sleep',
    ])->fetchField();
    $this->assertSame('5', $post_matches, '5 rows should be set to sleep');

    $this->assertSame(3, (int) $post_matches - (int) $pre_matches, '3 rows should have been changed');
  }

  /**
   * Confirm that we can update values in a column with special name.
   */
  public function testSpecialColumnUpdate(): void {
    $this->connection->update('select')
      ->fields([
        'update' => 'New update value',
      ])
      ->condition('id', 1)
      ->execute();

    $saved_value = $this->connection->query('SELECT [update] FROM {select} WHERE [id] = :id', [':id' => 1])->fetchField();
    $this->assertEquals('New update value', $saved_value);
  }

  /**
   * Updating a not existing table throws a DatabaseExceptionWrapper.
   */
  public function testUpdateNonExistingTable(): void {
    $this->expectException(DatabaseExceptionWrapper::class);
    $this->connection->update('a-table-that-does-not-exist')
      ->fields([
        'update' => 'New update value',
      ])
      ->condition('id', 1)
      ->execute();
  }

  /**
   * Updating a serial field throws a IntegrityConstraintViolationException.
   */
  public function testUpdateValueInSerial(): void {
    $this->expectException(IntegrityConstraintViolationException::class);
    $this->connection->update('test')
      ->fields(['id' => 2])
      ->condition('id', 1)
      ->execute();
  }

  /**
   * Tests the Update::__toString() method.
   */
  public function testToString(): void {
    // Prepare query for testing.
    $query = $this->connection->update('test')
      ->fields(['a' => 27, 'b' => 42])
      ->condition('c', [1, 2], 'IN');

    // Confirm placeholders are present.
    $query_string = (string) $query;
    $this->assertStringContainsString(':db_update_placeholder_0', $query_string);
    $this->assertStringContainsString(':db_update_placeholder_1', $query_string);
    $this->assertStringContainsString(':db_condition_placeholder_0', $query_string);
    $this->assertStringContainsString(':db_condition_placeholder_1', $query_string);

    // Test arguments.
    $expected = [
      ':db_update_placeholder_0' => 27,
      ':db_update_placeholder_1' => 42,
      ':db_condition_placeholder_0' => 1,
      ':db_condition_placeholder_1' => 2,
    ];
    $this->assertEquals($expected, $query->arguments());
  }

}
