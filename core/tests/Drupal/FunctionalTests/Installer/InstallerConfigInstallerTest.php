<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use Drupal\Core\Database\Database;

/**
 * Verifies that the installer creates the configuration as expected.
 *
 * @group Installer
 */
class InstallerConfigInstallerTest extends InstallerTestBase {

  /**
   * The configuration name.
   *
   * @var string
   */
  protected $configName = 'system.installer';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUpSite(): void {
    // Get the database connection.
    $database = Database::getConnection();

    // Query the config table to check if the config exists.
    $query = $database->select('config', 'c')
      ->fields('c', ['name'])
      ->condition('name', $this->configName)
      ->execute();

    // Fetch the result.
    $config_exists = $query->fetchField();

    // Assert that the config exists.
    $this->assertTrue($config_exists !== FALSE, 'The installer config does not exist.');

    parent::setUpSite();
  }

  /**
   * Verify if the installer config was removed after the installation.
   */
  public function testInstaller(): void {
    $this->assertFalse(
      $this->container->get('config.storage')->exists($this->configName),
      'The installer config exists after the install.'
    );
  }

}
