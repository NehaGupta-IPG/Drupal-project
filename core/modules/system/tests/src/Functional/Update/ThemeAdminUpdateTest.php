<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * @group system
 * @group Update
 * @group legacy
 * @covers system_post_update_set_theme_admin_to_null
 */
class ThemeAdminUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-10.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests the upgrade path for updating empty admin to NULL.
   */
  public function testLangcodesAddedToSimpleConfig(): void {
    $this->expectDeprecation("Empty system.theme admin key isn't allowed.");
    $this->config('system.theme')->set('admin', '')->save();
    // Run Updates and no errors should occur.
    $this->runUpdates();
    $this->assertNull($this->config('system.theme')->get('admin'));
  }

}
