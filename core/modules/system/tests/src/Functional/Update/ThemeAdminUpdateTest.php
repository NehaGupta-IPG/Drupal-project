<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * @group system
 * @group Update
 * @group legacy
 * @covers \Drupal\system\EventSubscriber\UpdateEmptyAdminTheme
 */
class ThemeAdminUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-10.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests the upgrade path for updating empty admin theme to NULL.
   */
  public function testEmptyAdminThemeUpdate(): void {
    $this->expectDeprecation("Setting the admin theme to an empty string is deprecated in drupal:11.0.0 and will not be allowed in drupal:12.0.0. See https://www.drupal.org/node/3441503");
    $this->config('system.theme')->set('admin', '')->save();
    // Run Updates and no errors should occur.
    $this->runUpdates();
    $this->assertNull($this->config('system.theme')->get('admin'));
  }

}
