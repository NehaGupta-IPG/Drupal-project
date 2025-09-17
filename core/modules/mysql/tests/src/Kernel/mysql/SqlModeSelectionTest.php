<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Kernel\mysql;

use Drupal\KernelTests\Core\Database\DriverSpecificDatabaseTestBase;
use Drupal\mysql\Driver\Database\mysql\SqlMode;

/**
 * Tests our ability to select a set of Sql modes.
 *
 * This test sets some mode values to TRUE, and some to FALSE,
 * and then checks to see if the result comes out as expected,
 * taking into account things such as Drupal default modes and
 * combination modes.
 *
 * @group Database
 */
class SqlModeSelectionTest extends DriverSpecificDatabaseTestBase {

  /**
   * Tests to see if the modes that were set match what we requested.
   */
  public function testThatCorrectSqlModesAreEnabled(): void {
    // Assert that we successfully added ONLY_FULL_GROUP_BY to the SQL mode.
    $query = $this->connection->query('SELECT @@SESSION.sql_mode session;');
    $modes = $query->fetchObject()->session;
    // We explicitly removed the Drupal default mode TRADITIONAL,
    // so it should not be set.
    $this->assertStringNotContainsString('TRADITIONAL', $modes);
    // We explicitly added the modes ERROR_FOR_DIVISION_BY_ZERO and
    // NO_ENGINE_SUBSTITUTION, so it should not be set.
    $this->assertStringContainsString('ERROR_FOR_DIVISION_BY_ZERO', $modes);
    $this->assertStringContainsString('NO_ENGINE_SUBSTITUTION', $modes);
    // STRICT_ALL_TABLES is set implicitly by the mode TRADITIONAL,
    // but since we did not set TRADITIONAL, we expect that STRICT_ALL_TABLES
    // should not be set either.
    $this->assertStringNotContainsString('STRICT_ALL_TABLES', $modes);
    // We attempted to remove the mode ANSI_QUOTES, but since the
    // Drupal default mode ANSI is still set, we expect that ANSI_QUOTES
    // should also be set.
    $this->assertStringContainsString('ANSI_QUOTES', $modes);
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
      $info['default']['sql_mode_options'] = [
        // Disable the mode TRADITIONAL, that Drupal enables by default.
        SqlMode::TRADITIONAL => FALSE,
        // Turn back on two of the modes usually implicitly enabled by TRADITIONAL.
        'ERROR_FOR_DIVISION_BY_ZERO' => TRUE,
        'NO_ENGINE_SUBSTITUTION' => TRUE,
        // The line below is a mistake. We are trying to disable
        // the mode ANSI_QUOTES, but this mode is not implicitly set
        // by Drupal; instead, Drupal sets the mode ANSI, which in
        // turn sets ANSI_QUOTES. It would be necessary to disable
        // the ANSI mode, if disabling ANSI_QUOTES was desired.
        'ANSI_QUOTES' => FALSE,
      ];
    }

    return $info;
  }

}
