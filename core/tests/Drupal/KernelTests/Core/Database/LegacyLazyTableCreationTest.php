<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Batch\BatchStorage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Tests the deprecated lazy table creation helpers of database backends.
 */
#[Group('Database')]
#[Group('legacy')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
class LegacyLazyTableCreationTest extends DatabaseTestBase {

  /**
   * Tests that BatchStorage::ensureTableExists() still creates its table.
   */
  public function testBatchStorageEnsureTableExists(): void {
    $storage = new BatchStorage($this->connection, $this->createStub(SessionInterface::class), $this->createStub(CsrfTokenGenerator::class), $this->createStub(TimeInterface::class));
    $this->expectUserDeprecationMessage(BatchStorage::class . '::ensureTableExists() is deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. Use \Drupal\Core\Database\Connection::executeAndCreateTableIfNeeded() instead. See https://www.drupal.org/node/3489185');
    (new \ReflectionMethod($storage, 'ensureTableExists'))->invoke($storage);
    $this->assertTrue($this->connection->schema()->tableExists('batch'));
  }

}
