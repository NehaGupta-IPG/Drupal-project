<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\ExceptionHandler;
use Drupal\mysqli\Driver\Database\mysqli\ExceptionHandler as MysqliExceptionHandler;
use Drupal\pgsql\Driver\Database\pgsql\ExceptionHandler as PgsqlExceptionHandler;
use Drupal\sqlite\Driver\Database\sqlite\ExceptionHandler as SqliteExceptionHandler;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;

// cspell:ignore typname

/**
 * Tests recognition of table creation errors by the exception handlers.
 */
#[CoversClass(ExceptionHandler::class)]
#[CoversClass(MysqliExceptionHandler::class)]
#[CoversClass(PgsqlExceptionHandler::class)]
#[CoversClass(SqliteExceptionHandler::class)]
#[Group('Database')]
class ExceptionHandlerTest extends UnitTestCase {

  /**
   * Tests that driver-specific errors are recognized only by that driver.
   */
  #[TestWith([ExceptionHandler::class, '42S02', 1146, 'Missing table', TRUE, FALSE])]
  #[TestWith([ExceptionHandler::class, '42S01', 1050, 'Table exists', FALSE, TRUE])]
  #[TestWith([PgsqlExceptionHandler::class, '42P01', 7, 'Missing table', TRUE, FALSE])]
  #[TestWith([PgsqlExceptionHandler::class, '42P07', 7, 'Table exists', FALSE, TRUE])]
  #[TestWith([PgsqlExceptionHandler::class, '42710', 7, 'Type exists', FALSE, TRUE])]
  #[TestWith([PgsqlExceptionHandler::class, '23505', 7, 'pg_type_typname_nsp_index', FALSE, TRUE])]
  #[TestWith([PgsqlExceptionHandler::class, '23505', 7, 'other_constraint', FALSE, FALSE])]
  #[TestWith([PgsqlExceptionHandler::class, '42501', 7, 'Permission denied', FALSE, FALSE])]
  #[TestWith([SqliteExceptionHandler::class, 'HY000', 1, 'no such table: lazy', TRUE, FALSE])]
  #[TestWith([SqliteExceptionHandler::class, 'HY000', 1, 'table lazy already exists', FALSE, TRUE])]
  #[TestWith([SqliteExceptionHandler::class, 'HY000', 1, 'syntax error', FALSE, FALSE])]
  #[TestWith([SqliteExceptionHandler::class, 'HY000', 8, 'attempt to write a readonly database', FALSE, FALSE])]
  public function testDriverErrors(string $class, string $state, int $code, string $message, bool $missing, bool $race): void {
    $handler = new $class();
    $previous = new \PDOException($message);
    $previous->errorInfo = [$state, $code, $message];
    $exception = new DatabaseExceptionWrapper($message, 0, $previous);
    $this->assertSame($missing, $handler->isMissingTableException($exception));
    $this->assertSame($race, $handler->isTableCreationRace($exception));
  }

  /**
   * Tests that mysqli errors are recognized.
   */
  #[TestWith(['42S02', 1146, TRUE, FALSE])]
  #[TestWith(['42S01', 1050, FALSE, TRUE])]
  #[TestWith(['42000', 1142, FALSE, FALSE])]
  public function testMysqliErrors(string $state, int $code, bool $missing, bool $race): void {
    $handler = new MysqliExceptionHandler();
    $previous = new \mysqli_sql_exception('Error', $code);
    (new \ReflectionProperty($previous, 'sqlstate'))->setValue($previous, $state);
    $exception = new DatabaseExceptionWrapper('Error', 0, $previous);
    $this->assertSame($missing, $handler->isMissingTableException($exception));
    $this->assertSame($race, $handler->isTableCreationRace($exception));
  }

}
