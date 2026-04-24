<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\Core\Database\Database;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the upgrade path for adding the data_query JSON column to config.
 *
 * @see system_update_11400()
 * @see system_update_11401()
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class ConfigJsonMigrationUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
      __DIR__ . '/../../../../tests/fixtures/update/install-mysqli.php',
    ];
  }

  /**
   * Tests that a data_query JSON column is added alongside the existing data.
   */
  public function testConfigDataQueryMigration(): void {
    $connection = Database::getConnection();
    $schema = $connection->schema();

    // Before the update, config data is stored as serialized PHP in 'data'.
    $data = $connection->select('config', 'c')
      ->fields('c', ['data'])
      ->condition('name', 'system.site')
      ->execute()
      ->fetchField();
    $this->assertIsString($data);
    $this->assertStringStartsWith('a:', $data, 'Config data is serialized PHP before update.');

    // The data_query column should not exist yet.
    $this->assertFalse($schema->fieldExists('config', 'data_query'));

    $this->runUpdates();

    // After the update, 'data' is still serialized PHP.
    $data = $connection->select('config', 'c')
      ->fields('c', ['data'])
      ->condition('name', 'system.site')
      ->execute()
      ->fetchField();
    $this->assertIsString($data);
    $this->assertStringStartsWith('a:', $data, 'Config data is still serialized PHP after update.');

    // The data_query column now exists and contains valid JSON.
    $this->assertTrue($schema->fieldExists('config', 'data_query'));
    $data_query = $connection->select('config', 'c')
      ->fields('c', ['data_query'])
      ->condition('name', 'system.site')
      ->execute()
      ->fetchField();
    $this->assertIsString($data_query);
    $decoded = json_decode($data_query, TRUE);
    $this->assertIsArray($decoded, 'data_query contains valid JSON after update.');
    $this->assertArrayHasKey('name', $decoded);
    $this->assertArrayHasKey('uuid', $decoded);

    // Config entity key-value lookup store entries should be removed.
    $entries = $connection->select('key_value', 'kv')
      ->fields('kv', ['collection'])
      ->condition('collection', 'config.entity.key_store.%', 'LIKE')
      ->distinct()
      ->execute()
      ->fetchCol();
    $this->assertEmpty($entries, 'No config.entity.key_store.* key-value entries remain.');
  }

}
