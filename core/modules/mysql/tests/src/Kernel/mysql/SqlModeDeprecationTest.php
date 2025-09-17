<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Kernel\mysql;

use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests whether a deprecation warning is thrown when sql_mode is used.
 *
 * @group legacy
 */
class SqlModeDeprecationTest extends KernelTestBase {

  /**
   * Tests behavior when sql_mode is used.
   */
  public function testSqlModeDeprecated(): void {
    // Find the current SUT database driver from the connection info. If that
    // is not the one the test requires, skip before test database
    // initialization so to save cycles.
    $this->root = static::getDrupalRoot();
    $connectionInfo = $this->getDatabaseConnectionInfo();
    if ($connectionInfo['default']['driver'] !== 'mysql') {
      $this->markTestSkipped("This test only runs for the database driver 'mysql'. Current database driver is '{$connectionInfo['default']['driver']}'.");
    }

    // We expect that getConnection() should trigger a deprecation warning
    // when called, if 'sql_mode' is in use.
    $this->expectDeprecation("The 'sql_mode' database command is deprecated in drupal:11.1.0 and will be removed in drupal:12.0.0. Use an array of options in 'sql_mode_options' instead. See https://www.drupal.org/node/3403416");
    $connection = Database::getConnection();
  }

  /**
   * {@inheritdoc}
   */
  protected function getDatabaseConnectionInfo() {
    $info = parent::getDatabaseConnectionInfo();

    // Add an 'sql_mode' option to 'init_commands' in the connectionInfo.
    $info['default']['init_commands'] = ['sql_mode' => "SET sql_mode = 'ANSI,TRADITIONAL'"];

    return $info;
  }

}
