<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Kernel\mysql;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\State\State;
use Drupal\KernelTests\Core\Database\DriverSpecificDatabaseTestBase;
use Drupal\TestTools\Random;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * MySQL-specific connection tests.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class ConnectionTest extends DriverSpecificDatabaseTestBase {

  /**
   * Ensure that you cannot execute multiple statements on MySQL.
   */
  public function testMultipleStatementsForNewPhp(): void {
    $this->expectException(DatabaseExceptionWrapper::class);
    Database::getConnection('default', 'default')->query('SELECT * FROM {test}; SELECT * FROM {test_people}', [], ['allow_delimiter_in_query' => TRUE]);
  }

  /**
   * Tests reconnection.
   */
  public function testReconnection(): void {
    $value = Random::string();
    $state = $this->container->get('state');
    assert($state instanceof State);
    $state->set('test', $value);
    $connection_id = $this->connection->query('SELECT CONNECTION_ID()')->fetchField();
    // expectException terminates the test so it can't be used here.
    try {
      $this->connection->query('KILL :connection_id', [':connection_id' => $connection_id]);
      $this->fail('The connection should be dead.');
    }
    catch (DatabaseException) {

    }
    try {
      // Bypass the reconnection mechanism.
      $this->connection->getClientConnection()->query('SELECT 1');
      $this->fail('The connection should be dead.');
    }
    catch (\Exception) {

    }
    $this->assertSame($value, $state->get('test'));
  }

}
