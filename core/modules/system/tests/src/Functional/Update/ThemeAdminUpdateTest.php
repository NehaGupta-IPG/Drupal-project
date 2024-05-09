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
    $this->expectDeprecation("Setting empty 'system.theme admin' key is deprecated in drupal:11.0.0-alpha1 and would not be allowed in drupal:11.0.0-alpha2. See https://www.drupal.org/i/3441503");
    $this->config('system.theme')->set('admin', '')->save();
    // Run Updates and no errors should occur.
    $this->runUpdates();
    $this->assertNull($this->config('system.theme')->get('admin'));
  }

}
