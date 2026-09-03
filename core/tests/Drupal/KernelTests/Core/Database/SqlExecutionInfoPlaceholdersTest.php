<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\DatabaseExceptionBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that failing queries report the values they were executed with.
 *
 * Every driver has to hand the bound values to its exception handler, whether
 * it passes them to \Drupal\Core\Database\StatementInterface::execute() or
 * binds them on the client statement beforehand.
 *
 * @see \Drupal\Core\Database\ExceptionHandler::handleExecutionException()
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class SqlExecutionInfoPlaceholdersTest extends DatabaseTestBase {

  /**
   * Tests the placeholders reported by a failing insert.
   */
  public function testInsertReportsPlaceholders(): void {
    try {
      // The 'name' column is NOT NULL, which every database rejects.
      $this->connection->insert('test')
        ->fields([
          'name' => NULL,
          'age' => 47,
          'job' => 'Drummer',
        ])
        ->execute();
      $this->fail('The insert should have failed.');
    }
    catch (DatabaseExceptionBase $e) {
      $this->assertPlaceholders([NULL, 47, 'Drummer'], $e);
    }
  }

  /**
   * Tests the placeholders reported by a failing update.
   */
  public function testUpdateReportsPlaceholders(): void {
    try {
      // 'John' is the sample row aged 25, so a row is matched and updated.
      $this->connection->update('test')
        ->fields(['name' => NULL])
        ->condition('age', 25)
        ->execute();
      $this->fail('The update should have failed.');
    }
    catch (DatabaseExceptionBase $e) {
      // The values of the updated fields and of the condition are both
      // reported.
      $this->assertPlaceholders([NULL, 25], $e);
    }
  }

  /**
   * Tests the placeholders reported by a failing upsert.
   */
  public function testUpsertReportsPlaceholders(): void {
    try {
      // Row 1 exists, so this upsert updates it, and sets a NOT NULL column
      // to NULL while doing so.
      $this->connection->upsert('test')
        ->key('id')
        ->fields(['id', 'name', 'age', 'job'])
        ->values([
          'id' => 1,
          'name' => NULL,
          'age' => 47,
          'job' => 'Drummer',
        ])
        ->execute();
      $this->fail('The upsert should have failed.');
    }
    catch (DatabaseExceptionBase $e) {
      $this->assertPlaceholders([1, NULL, 47, 'Drummer'], $e);
    }
  }

  /**
   * Asserts the values a failed query reports, in the order they were bound.
   *
   * The keys are not compared: a driver emitting '?' placeholders reports the
   * values 0-indexed, while a driver emitting named placeholders reports them
   * keyed by name. Named keys are checked against the statement instead, since
   * a name that is not in the statement belongs to no placeholder at all.
   *
   * @param array $expected
   *   The bound values, in the order they appear in the statement.
   * @param \Drupal\Core\Database\DatabaseExceptionBase $e
   *   The exception thrown by the failing query.
   */
  protected function assertPlaceholders(array $expected, DatabaseExceptionBase $e): void {
    $this->assertNotNull($e->info, 'The exception carries SQL execution information.');
    $this->assertSame($expected, array_values($e->info->placeholders));
    foreach (array_keys($e->info->placeholders) as $name) {
      if (is_string($name)) {
        $this->assertStringContainsString($name, $e->info->sqlString);
      }
    }
  }

}
