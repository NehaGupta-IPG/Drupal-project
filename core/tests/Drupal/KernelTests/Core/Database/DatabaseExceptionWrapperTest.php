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
    try {
      Database::getConnection()->query('SELECT * FROM {does_not_exist} WHERE [not_a_column] = :not_a_value', [':not_a_value' => 'not a value']);
      $this->fail('Query should have failed.');
    }
    catch (\Throwable $e) {
      $this->assertInstanceOf(DatabaseExceptionWrapper::class, $e);
      $this->assertMatchesRegularExpression('/SELECT \* FROM ".*does_not_exist" WHERE "not_a_column" = :not_a_value/', $e->info->sqlString);
      $this->assertSame([':not_a_value' => 'not a value'], $e->info->placeholders);
      // The message keeps the statement in compact form, so that it still
      // reaches the log and any consumer rendering the throwable itself.
      $this->assertStringContainsString('does_not_exist', $e->getMessage());
      $this->assertStringContainsString(":not_a_value='not a value'", $e->getMessage());
      $this->assertStringNotContainsString("\n", $e->getMessage());
      $this->assertSame([
        'fetch' => FetchAs::Object,
        'allow_delimiter_in_query' => FALSE,
        'allow_square_brackets' => FALSE,
        'pdo' => [],
      ], $e->info->options);
    }
  }

}
