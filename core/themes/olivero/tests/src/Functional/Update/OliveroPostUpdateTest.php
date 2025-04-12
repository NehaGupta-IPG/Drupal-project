<?php

declare(strict_types=1);

namespace Drupal\Tests\olivero\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the update path for Olivero.
 *
 * @group Update
 * @group #slow
 */
class OliveroPostUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../../modules/system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests update olivero third party settings without shortcut installed.
   */
  public function testOliveroThirdPartySettingsWithoutShortcut(): void {
    $config = $this->config('olivero.settings');
    $this->assertNotEmpty($config->get('third_party_settings'));

    $shortcutStorage = \Drupal::entityTypeManager()->getStorage('shortcut');

    // Delete all shortcut entities.
    $shortcuts = $shortcutStorage->loadMultiple();
    foreach ($shortcuts as $shortcut) {
      $shortcut->delete();
    }

    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $moduleInstaller */
    $moduleInstaller = \Drupal::service('module_installer');
    $moduleInstaller->uninstall(['shortcut']);

    $config = $this->config('olivero.settings');
    $this->assertNotEmpty($config->get('third_party_settings'));

    // Run updates.
    $this->runUpdates();

    $config = $this->config('olivero.settings');
    $this->assertNull($config->get('third_party_settings'));
  }

  /**
   * Tests update olivero third party settings with shortcut installed.
   */
  public function testOliveroThirdPartySettingsWithShortcut(): void {
    $config = $this->config('olivero.settings');
    $this->assertNotEmpty($config->get('third_party_settings'));

    // Run updates.
    $this->runUpdates();

    $config = $this->config('olivero.settings');
    $this->assertNotEmpty($config->get('third_party_settings'));
  }

}
