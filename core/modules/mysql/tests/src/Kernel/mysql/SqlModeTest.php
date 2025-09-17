<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Kernel\mysql;

use Drupal\KernelTests\Core\Database\DriverSpecificDatabaseTestBase;
use Drupal\mysql\Driver\Database\mysql\SqlMode;

/**
 * Tests compatibility of the MySQL driver when disabling the ANSI_QUOTES sql_mode option.
 *
 * Also contains a control test that confirms the behavior of the database query parser
 * when ONLY_FULL_GROUP_BY is not enabled.
 *
 * @group Database
 */
class SqlModeTest extends DriverSpecificDatabaseTestBase {

  /**
   * Tests quoting identifiers in queries.
   */
  public function testQuotingIdentifiers(): void {
    // Use SQL-reserved words for both the table and column names.
    $query = $this->connection->query('SELECT [update] FROM {select}');
    $this->assertEquals('Update value 1', $query->fetchObject()->update);
    $this->assertStringContainsString('SELECT `update` FROM `', $query->getQueryString());
  }

  /**
   * Tests behavior when ONLY_FULL_GROUP_BY is not set.
   */
  public function testOnlyFullGroupByDisabled(): void {
    // No SQL modes are set, so ONLY_FULL_GROUP_BY is therefore not set, so this query should succeed.
    // Note that this is the same query that fails in testOnlyFullGroupByEnabled, the only difference
    // being that in the later test, ONLY_FULL_GROUP_BY is set.
    $query = $this->connection->query('SELECT name, job FROM {test} GROUP BY job');
    $this->assertEquals('Singer', $query->fetchObject()->job);
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
      // In order to disable ANSI_QUOTES, we must disable the ANSI meta-mode,
      // which is enabled by default in Connection::open(), as setting ANSI
      // also sets ANSI_QUOTES.
      $info['default']['sql_mode_options'][SqlMode::ANSI] = FALSE;
      // We disable TRADITIONAL as well, simply to ensure that the driver does
      // not set any modes for this test.
      $info['default']['sql_mode_options'][SqlMode::TRADITIONAL] = FALSE;
    }

    return $info;
  }

}
