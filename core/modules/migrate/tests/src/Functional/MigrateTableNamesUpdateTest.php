<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate\Functional;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * Tests the update of migrate table names.
 */
#[Group('migrate')]
#[IgnoreDeprecations]
class MigrateTableNamesUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
      __DIR__ . '/../../fixtures/drupal-11-update-110300.php',
    ];
  }

  /**
   * Test upgrade path for table names.
   */
  public function testUpdateTableNames(): void {
    $database = \Drupal::database();

    // Confirm the source tables exist.
    $table_names = [
      ['migrate_map_short_name', 6],
      ['migrate_message_short_name', 4],
      ['migrate_map_migration_with_a_long_id_for_testing', 6],
      ['migrate_message_migration_with_a_long_id_for_testin', 4],
      // migration_with_a_very_long_id_for_testing
      ['migrate_map_migration_with_a_very_long_id_for_testi', 6],
      ['migrate_message_migration_with_a_very_long_id_for_t', 4],
      ['migrate_map_horizontal_horizontal_horizontal_horizo', 6],
      ['migrate_message_horizontal_horizontal_horizontal_ho', 4],
    ];

    foreach ($table_names as $table_data) {
      $table_name = $table_data[0];
      $this->assertTrue($database->schema()->tableExists($table_name), "Table '$table_name' does not exist");
      $count = (int) $database->select($table_name)
        ->countQuery()
        ->execute()
        ->fetchField();
      $this->assertEquals($table_data[1], $count, "Table '$table_name' Failed asserting that $count matches expected $table_data[1]");
    }

    $this->runUpdates();

    // Confirm the updated tables names exist.
    $table_names = [
      // Short names do not change. Migration 'short_name'.
      ['migrate_map_short_name', 6],
      ['migrate_message_short_name', 4],
      // Only the legacy message table name is too long, then both table names
      // are changed. Migration 'migration_with_a_long_id_for_testing'.
      ['migrate_map_2b418e0902', 6],
      ['migrate_message_2b418e0902', 4],
      // Both the map and message legacy message table names are too long, both
      // table names are changed. Migration
      // 'migration_with_a_very_long_id_for_testing'.
      ['migrate_map_bfcc4b1c96', 6],
      ['migrate_message_bfcc4b1c96', 4],
      // Two migrations with the same map and message table names. Migration
      // 'horizontal_horizontal_horizontal_horizontal_horizontal_images' and
      // 'horizontal_horizontal_horizontal_horizontal_horizontal_posts'.
      ['migrate_map_529ad57a1f', 6],
      ['migrate_message_529ad57a1f', 4],
      ['migrate_map_39b601bb8d', 6],
      ['migrate_message_39b601bb8d', 4],
    ];

    foreach ($table_names as $table_data) {
      $table_name = $table_data[0];
      $this->assertTrue($database->schema()->tableExists($table_name), "Table '$table_name' does not exist");
      $count = (int) $database->select($table_name)
        ->countQuery()
        ->execute()
        ->fetchField();
      $this->assertEquals($table_data[1], $count, "Table '$table_name' Failed asserting that $count matches expected $table_data[1]");
    }

    // Confirm the source tables for tables that were copied have been deleted.
    $table_names = [
      'migrate_map_migration_with_a_long_id_for_testing',
      // Trimmed to 51 characters.
      'migrate_message_migration_with_a_long_id_for_testin',
      'migrate_map_migration_with_a_very_long_id_for_testi',
      'migrate_message_migration_with_a_very_long_id_for_t',
      'migrate_map_horizontal_horizontal_horizontal_horizo',
      'migrate_message_horizontal_horizontal_horizontal_ho',
    ];

    foreach ($table_names as $table_name) {
      $this->assertFalse($database->schema()->tableExists($table_name), "Table '$table_name' exists");
    }
  }

}
