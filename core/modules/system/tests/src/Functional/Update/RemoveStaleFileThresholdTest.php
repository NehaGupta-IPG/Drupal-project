<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

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
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests that the orphan stale_file_threshold key is removed.
   */
  public function testRunUpdates(): void {
    // The 10.3.0 fixture has stale_file_threshold set to its default value.
    $this->assertSame(2592000, \Drupal::config('system.performance')->get('stale_file_threshold'));
    $this->runUpdates();
    $this->assertNull(\Drupal::config('system.performance')->get('stale_file_threshold'));
  }

}
