<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\Core\Database\Database;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the upgrade path for removing system.performance.stale_file_threshold.
 *
 * @see system_update_12002()
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class RemoveStaleFileThresholdTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-11.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests that the orphan stale_file_threshold key is removed.
   */
  public function testRunUpdates(): void {
    // Inject the orphan stale_file_threshold key directly into the config
    // table to simulate sites that carry it forward from a pre-10.2 install.
    // The schema has not declared this key since #3425419 landed in 11.0,
    // so the regular ConfigFactory::save() path would reject it as
    // unsupported under strict typed-config validation. Writing directly
    // to the config table bypasses that gate, which is precisely the
    // legitimate pre-existing state this update hook exists to clean up.
    $connection = Database::getConnection();
    $serialized = $connection->select('config', 'c')
      ->fields('c', ['data'])
      ->condition('name', 'system.performance')
      ->execute()
      ->fetchField();
    $data = unserialize($serialized);
    $data['stale_file_threshold'] = 2592000;
    $connection->update('config')
      ->fields(['data' => serialize($data)])
      ->condition('name', 'system.performance')
      ->execute();

    // Invalidate cached copies so the read below reflects our DB injection.
    \Drupal::cache('config')->deleteAll();
    \Drupal::configFactory()->reset();

    $this->assertSame(2592000, \Drupal::config('system.performance')->get('stale_file_threshold'));
    $this->runUpdates();
    $this->assertNull(\Drupal::config('system.performance')->get('stale_file_threshold'));
  }

}
