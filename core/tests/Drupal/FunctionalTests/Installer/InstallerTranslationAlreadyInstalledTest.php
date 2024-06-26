<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

/**
 * Tests 'already installed' message is shown appropriately.
 *
 * @group Installer
 */
class InstallerTranslationAlreadyInstalledTest extends InstallerTranslationTest {

  /**
   * Confirms that visiting the installer does not break things post-install.
   */
  public function testVisitInstallerAlreadyInstalled(): void {
    $this->drupalGet('/core/install.php');
    $this->assertSession()->pageTextContains('Drupal already installed');

    // Without the change in https://www.drupal.org/project/drupal/issues/3455853
    // 'Drupal already installed' would not be shown if the state gets
    // set to an earlier translation related install task.
    \Drupal::state()->set('install_task', 'install_import_translations');
    $this->drupalGet('/core/install.php');
    $this->assertSession()->pageTextContains('Drupal already installed');
  }

}
