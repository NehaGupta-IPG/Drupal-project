<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate\Functional;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the update of migrate table names.
 *
 * @group legacy
 */
class MigrateUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../system/tests/fixtures/update/drupal-8.8.0.bare.standard.php.gz',
      __DIR__ . '/../../fixtures/drupal-9-update-9201.php',
    ];
  }

  /**
   * Test upgrade path for table names.
   */
  public function testUpdateTableNames(): void {
    $this->runUpdates();

    // Confirm the updated tables names exist.
    $table_names = [
      'migrate_map_short_name',
      'migrate_message_short_name',
      'migrate_map_migration_with_a_very_168704cb3313a371c',
      'migrate_message_migration_with_a__168704cb3313a371c',
      'migrate_map_migration_with_a_long_id_for_testing',
      'migrate_message_migration_with_a__7f1f88e1a765a4fcb',
    ];
    $database = \Drupal::database();
    foreach ($table_names as $table_name) {
      $this->assertTrue($database->schema()->tableExists($table_name), "Table '$table_name' does not exist");
    }
  }

}
