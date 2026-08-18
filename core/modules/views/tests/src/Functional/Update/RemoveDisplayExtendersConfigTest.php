<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the upgrade path for removing display extenders config.
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
#[IgnoreDeprecations]
class RemoveDisplayExtendersConfigTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests the upgrade path for removing system.file.path key.
   */
  public function testRunUpdates(): void {
    $this->expectUserDeprecationMessage('Drupal\\node\\Plugin\\Search\\NodeSearch is deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Instead, use \\Drupal\\search_node\\Plugin\\Search\\SearchNode. See https://www.drupal.org/node/3590298');
    $this->expectUserDeprecationMessage('Drupal\\user\\Plugin\\Search\\UserSearch is deprecated in drupal:11.5.0 and is removed from drupal:12.0.0. Instead, use \\Drupal\\search_user\\Plugin\\Search\\SearchUser See https://www.drupal.org/node/3590230');
    $this->assertIsArray(\Drupal::config('views.settings')->get('display_extenders'));
    $this->runUpdates();
    $this->assertNull(\Drupal::config('views.settings')->get('display_extenders'));
  }

}
