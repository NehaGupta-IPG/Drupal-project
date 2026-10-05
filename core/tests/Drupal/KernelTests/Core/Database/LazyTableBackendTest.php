<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Batch\BatchStorage;
use Drupal\Core\Flood\DatabaseBackend;
use Drupal\Core\Session\SessionHandler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Tests that backend callbacks delegate database error recovery to connections.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class LazyTableBackendTest extends DatabaseTestBase {

  /**
   * Constructs a backend without starting a real HTTP session.
   */
  private function backend(string $table): object {
    $time = $this->createStub(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1000000);
    return match ($table) {
      'batch' => new BatchStorage($this->connection, $this->createStub(SessionInterface::class), $this->createStub(CsrfTokenGenerator::class), $time),
      'flood' => new DatabaseBackend($this->connection, new RequestStack(), $time),
      'sessions' => new SessionHandler(new RequestStack(), $this->connection, $time),
    };
  }

  /**
   * Tests recovery from missing tables without backend exception handlers.
   */
  #[TestWith(['batch', 'load', [1], FALSE])]
  #[TestWith(['batch', 'delete', [1], NULL])]
  #[TestWith(['batch', 'update', [['id' => 1]], NULL])]
  #[TestWith(['batch', 'cleanup', [], NULL])]
  #[TestWith(['flood', 'clear', ['event', 'visitor'], NULL])]
  #[TestWith(['flood', 'clearByPrefix', ['event', 'visitor'], NULL])]
  #[TestWith(['flood', 'garbageCollection', [], NULL])]
  #[TestWith(['sessions', 'doRead', ['missing'], ''])]
  #[TestWith(['sessions', 'destroy', ['missing'], TRUE])]
  #[TestWith(['sessions', 'gc', [3600], 0])]
  public function testMissingTable(string $table, string $method, array $arguments, mixed $expected): void {
    $this->assertFalse($this->connection->schema()->tableExists($table));
    $this->assertSame($expected, $this->backend($table)->$method(...$arguments));
    $this->assertTrue($this->connection->schema()->tableExists($table));
  }

  /**
   * Tests that deprecated exception helpers retain their legacy behavior.
   */
  #[TestWith(['batch', BatchStorage::class])]
  #[TestWith(['flood', DatabaseBackend::class])]
  #[IgnoreDeprecations]
  public function testDeprecatedExceptionHelper(string $table, string $class): void {
    $backend = $this->backend($table);
    $method = new \ReflectionMethod($backend, 'catchException');
    $exception = new \RuntimeException('Original exception');
    $this->expectUserDeprecationMessage($class . '::catchException() is deprecated in drupal:12.0.0 and is removed from drupal:13.0.0. Use Connection::executeAndCreateTableIfNeeded() instead. See https://www.drupal.org/node/3626977');
    $method->invoke($backend, $exception);
    $this->connection->schema()->createTable($table, [
      'fields' => ['id' => ['type' => 'int']],
    ]);
    $this->expectExceptionObject($exception);
    $method->invoke($backend, $exception);
  }

}
