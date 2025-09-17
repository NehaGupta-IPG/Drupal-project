<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Kernel\mysql;

use Drupal\KernelTests\Core\Database\DriverSpecificDatabaseTestBase;
use Drupal\Core\Database\DatabaseExceptionWrapper;

/**
 * Tests compatibility of the MySQL driver when enabling the ONLY_FULL_GROUP_BY sql_mode option.
 *
 * @group Database
 */
class SqlModeOnlyFullGroupByTest extends DriverSpecificDatabaseTestBase {

  /**
   * Tests behavior when ONLY_FULL_GROUP_BY is set.
   */
  public function testOnlyFullGroupByEnabled(): void {
    // Assert that we successfully added ONLY_FULL_GROUP_BY to the SQL mode.
    $query = $this->connection->query('SELECT @@SESSION.sql_mode session;');
    $this->assertStringContainsString('ONLY_FULL_GROUP_BY', $query->fetchObject()->session);

    // ONLY_FULL_GROUP_BY is set, but this query should still work in that mode.
    $query = $this->connection->query('SELECT job FROM {test} GROUP BY job');
    $this->assertEquals('Singer', $query->fetchObject()->job);
    // We have set ONLY_FULL_GROUP_BY, so this query should fail.
    $this->expectException(DatabaseExceptionWrapper::class);
    $query = $this->connection->query('SELECT name, job FROM {test} GROUP BY job');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDatabaseConnectionInfo() {
    $info = parent::getDatabaseConnectionInfo();

    // This runs during setUp(), so is not yet skipped for non MySQL databases.
    // We defer skipping the test to later in setUp(), so that that can be
    // based on databaseType() rather than 'driver', but here all we have to go
    // on is 'driver'.
    if ($info['default']['driver'] === 'mysql') {
      // Set ONLY_FULL_GROUP_BY and confirm that doing so changes the query parsing behavior.
      $info['default']['sql_mode_options']['ONLY_FULL_GROUP_BY'] = TRUE;
    }

    return $info;
  }

}
