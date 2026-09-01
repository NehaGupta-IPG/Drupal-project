<?php

declare(strict_types=1);

namespace Drupal\Tests\dblog\Functional;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests update functions for the Database Logging module.
 */
#[Group('dblog')]
#[RunTestsInSeparateProcesses]
class UpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests that, after update 11100, the 'wid' column is an unsigned integer.
   */
  public function testLogEntryWithNegativeId(): void {
    global $base_root;

    // Before updates, a negative WID should be possible.
    $connection = Database::getConnection();
    $insert = $connection->insert('watchdog');
    $insert->fields([
      'wid'         => -1000,
      'message'     => 'Dblog test log message with negative WID',
      'type'        => 'test',
      'variables'   => '',
      'severity'    => RfcLogLevel::NOTICE,
      'uid'         => 1,
      'location'    => $base_root . \Drupal::request()->getRequestUri(),
      'hostname'    => $base_root,
      'timestamp'   => \Drupal::time()->getRequestTime(),
    ]);
    $insert->execute();

    $this->runUpdates();

    // After updates, a negative WID should not be allowed.
    $insert = $connection->insert('watchdog');
    $insert->fields([
      'wid'         => -1000,
      'message'     => 'Dblog test log message with negative WID',
      'type'        => 'test',
      'variables'   => '',
      'severity'    => RfcLogLevel::NOTICE,
      'uid'         => 1,
      'location'    => $base_root . \Drupal::request()->getRequestUri(),
      'hostname'    => $base_root,
      'timestamp'   => \Drupal::time()->getRequestTime(),
    ]);
    try {
      $insert->execute();
      $this->fail('After updates, a negative WID should not be allowed.');
    }
    catch (IntegrityConstraintViolationException | DatabaseExceptionWrapper) {
      // Expected.
    }
  }

}
