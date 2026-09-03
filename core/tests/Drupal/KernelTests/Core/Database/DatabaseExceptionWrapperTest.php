<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests exceptions thrown by queries.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class DatabaseExceptionWrapperTest extends KernelTestBase {

  /**
   * Tests the expected database exception thrown for inexistent tables.
   */
  public function testQueryThrowsDatabaseExceptionWrapperException(): void {
    // The catch is narrowed to the expected exception. A \Throwable catch would
    // also swallow the PHPUnit\Framework\AssertionFailedError thrown by
    // ::fail() below, and report it as a wrong exception class instead.
    try {
      Database::getConnection()->query('SELECT * FROM {does_not_exist} WHERE [not_a_column] = :not_a_value', [':not_a_value' => 'not a value']);
      $this->fail('Query should have failed.');
    }
    catch (DatabaseExceptionWrapper $e) {
      $this->assertMatchesRegularExpression('/SELECT \* FROM ".*does_not_exist" WHERE "not_a_column" = :not_a_value/', $e->info->sqlString);
      $this->assertSame([':not_a_value' => 'not a value'], $e->info->placeholders);
      // The message keeps the statement in compact form, so that it still
      // reaches the log and any consumer rendering the throwable itself. Only
      // the appended part is a single line: a driver message can span lines,
      // as PostgreSQL does when it points at the offending token.
      $this->assertStringContainsString('does_not_exist', $e->getMessage());
      $this->assertStringContainsString(":not_a_value='not a value'", $e->getMessage());
      $this->assertStringEndsWith(': ' . $e->info->toCompactString(), $e->getMessage());
      $this->assertStringNotContainsString("\n", $e->info->toCompactString());
      $this->assertSame([
        'fetch' => FetchAs::Object,
        'allow_delimiter_in_query' => FALSE,
        'allow_square_brackets' => FALSE,
        'pdo' => [],
      ], $e->info->options);
    }
  }

}
