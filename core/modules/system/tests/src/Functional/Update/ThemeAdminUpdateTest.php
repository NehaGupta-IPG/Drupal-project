<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * @group system
 * @group Update
 * @covers system_post_update_set_theme_admin_to_null
 */
class ThemeAdminUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-9.4.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests the upgrade path for updating empty admin to NULL.
   */
  public function testLangcodesAddedToSimpleConfig(): void {
    $this->config('system.theme')->set('admin', '')->save();
    $this->assertSame('', $this->config('system.theme')->get('admin'));
    $this->runUpdates();
    $this->assertNull($this->config('system.theme')->get('admin'));
  }

}
