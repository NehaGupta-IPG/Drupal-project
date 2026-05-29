<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_linkset\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the upgrade path moving linkset functionality to its own module.
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class MenuLinksetUpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests that menu_linkset is installed when the legacy flag was enabled.
   */
  public function testFlagEnabled(): void {
    \Drupal::configFactory()->getEditable('system.feature_flags')
      ->set('linkset_endpoint', TRUE)
      ->save();
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('menu_linkset'));

    $this->runUpdates();

    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('menu_linkset'));
    $this->assertTrue(\Drupal::config('system.feature_flags')->isNew(), 'The system.feature_flags config object has been deleted.');
  }

  /**
   * Tests that menu_linkset is not installed when the legacy flag was disabled.
   */
  public function testFlagDisabled(): void {
    $this->assertFalse(\Drupal::config('system.feature_flags')->get('linkset_endpoint'));
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('menu_linkset'));

    $this->runUpdates();

    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('menu_linkset'));
    $this->assertTrue(\Drupal::config('system.feature_flags')->isNew(), 'The system.feature_flags config object has been deleted.');
  }

}
