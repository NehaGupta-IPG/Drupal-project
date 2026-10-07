<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Exception\SchemaTableAlreadyExistsException;
use Drupal\Core\Database\Exception\SchemaTableDoesNotExistException;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Database\StatementInterface;
use Drupal\mysqli\Driver\Database\mysqli\Statement;
use Drupal\pgsql\Driver\Database\pgsql\Connection as PgsqlConnection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests driver normalization during preparation and execution.
 */
#[CoversClass(\Drupal\mysql\Driver\Database\mysql\ExceptionHandler::class)]
#[CoversClass(\Drupal\mysqli\Driver\Database\mysqli\ExceptionHandler::class)]
#[CoversClass(\Drupal\pgsql\Driver\Database\pgsql\ExceptionHandler::class)]
#[CoversClass(\Drupal\sqlite\Driver\Database\sqlite\ExceptionHandler::class)]
#[Group('Database')]
class TableExceptionHandlerTest extends UnitTestCase {

  /**
   * Tests native error classification and retained execution information.
   */
  #[DataProvider('errorProvider')]
  public function testNormalization(string $driver, string $state, int $native_code, string $detail, string $expected, bool $prepare): void {
    $class = "Drupal\\$driver\\Driver\\Database\\$driver\\ExceptionHandler";
    $handler = $driver === 'pgsql'
      ? (new \ReflectionClass(PgsqlConnection::class))->newInstanceWithoutConstructor()->exceptionHandler()
      : new $class();
    if ($driver === 'mysqli' && !class_exists(\mysqli_sql_exception::class)) {
      $this->markTestSkipped('The mysqli extension is unavailable.');
    }
    if ($driver === 'mysqli') {
      $exception = new \mysqli_sql_exception($detail, $native_code);
      $sqlstate = new \ReflectionProperty(\mysqli_sql_exception::class, 'sqlstate');
      $sqlstate->setValue($exception, $state);
    }
    else {
      $exception = new \PDOException($detail, 0, new \LogicException('Earlier failure'));
      $code = new \ReflectionProperty(\Exception::class, 'code');
      $code->setValue($exception, $state);
      $exception->errorInfo = [$state, $native_code, $detail];
    }
    $statement = $this->createStub($driver === 'mysqli' ? Statement::class : StatementInterface::class);
    $statement->method('getQueryString')->willReturn('SELECT * FROM missing');
    try {
      if ($prepare) {
        $handler->handleStatementException($exception, 'SELECT * FROM missing', ['test' => TRUE]);
      }
      else {
        $handler->handleExecutionException($exception, $statement, [':id' => 1], ['test' => TRUE]);
      }
      $this->fail('An exception must be thrown.');
    }
    catch (DatabaseExceptionWrapper $actual) {
      $this->assertSame($expected, $actual::class);
      $this->assertSame($exception, $actual->getPrevious());
      $this->assertStringStartsWith($detail . ': SELECT * FROM missing;', $actual->getMessage());
      $this->assertSame($expected === DatabaseExceptionWrapper::class ? 0 : (int) $state, $actual->getCode());
      $this->assertSame('SELECT * FROM missing', $actual->info->sqlString);
      $this->assertSame($prepare ? [] : [':id' => 1], $actual->info->placeholders);
      $this->assertSame(['test' => TRUE], $actual->info->options);
    }
  }

  /**
   * Provides native errors for both handler entry points.
   */
  public static function errorProvider(): array {
    $errors = [
      'mysql' => [
        ['42S02', 1146, 'Table missing', SchemaTableDoesNotExistException::class],
        ['42000', 1051, 'Unknown table', SchemaTableDoesNotExistException::class],
        ['42S01', 1050, 'Table exists', SchemaTableAlreadyExistsException::class],
        ['23000', 1062, 'Duplicate key', IntegrityConstraintViolationException::class],
        ['42S22', 1054, 'Unknown column', DatabaseExceptionWrapper::class],
      ],
      'pgsql' => [
        ['42P01', 7, 'Undefined table', SchemaTableDoesNotExistException::class],
        ['42P07', 7, 'Duplicate table', SchemaTableAlreadyExistsException::class],
        ['23505', 7, 'Duplicate key', IntegrityConstraintViolationException::class],
        ['42703', 7, 'Undefined column', DatabaseExceptionWrapper::class],
      ],
      'sqlite' => [
        ['HY000', 1, 'no such table: missing', SchemaTableDoesNotExistException::class],
        ['HY000', 1, 'table "test" already exists', SchemaTableAlreadyExistsException::class],
        [
          'HY000', 1, 'there is already another table or index with this name: test',
          SchemaTableAlreadyExistsException::class,
        ],
        ['HY000', 1, 'index test already exists', DatabaseExceptionWrapper::class],
        ['HY000', 1, 'trigger test already exists', DatabaseExceptionWrapper::class],
        ['HY000', 1, 'near "already exists": syntax error', DatabaseExceptionWrapper::class],
        ['HY000', 2, 'no such table: missing', DatabaseExceptionWrapper::class],
        ['23000', 19, 'Constraint violation', IntegrityConstraintViolationException::class],
      ],
    ];
    $errors['mysqli'] = $errors['mysql'];
    $cases = [];
    foreach ($errors as $driver => $rows) {
      foreach ($rows as $i => $row) {
        foreach ([FALSE, TRUE] as $prepare) {
          $cases["$driver-$i-" . (int) $prepare] = [$driver, ...$row, $prepare];
        }
      }
    }
    return $cases;
  }

  /**
   * Tests that exceptions from outside the native driver retain identity.
   */
  #[DataProvider('driverProvider')]
  public function testNonDriverException(string $driver, bool $prepare): void {
    $class = "Drupal\\$driver\\Driver\\Database\\$driver\\ExceptionHandler";
    $exception = new \RuntimeException('Original');
    try {
      if ($prepare) {
        (new $class())->handleStatementException($exception, 'SELECT 1');
      }
      else {
        (new $class())->handleExecutionException($exception, $this->createStub($driver === 'mysqli' ? Statement::class : StatementInterface::class));
      }
    }
    catch (\Exception $actual) {
      $this->assertSame($exception, $actual);
      return;
    }
    $this->fail('The original exception must be thrown.');
  }

  /**
   * Provides drivers and handler entry points.
   */
  public static function driverProvider(): array {
    $cases = [];
    foreach (['mysql', 'mysqli', 'pgsql', 'sqlite'] as $driver) {
      foreach ([FALSE, TRUE] as $prepare) {
        $cases[] = [$driver, $prepare];
      }
    }
    return $cases;
  }

}
