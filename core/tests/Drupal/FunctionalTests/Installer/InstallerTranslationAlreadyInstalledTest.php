<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests 'already installed' message is shown appropriately.
 */
#[Group('Installer')]
#[RunTestsInSeparateProcesses]
class InstallerTranslationAlreadyInstalledTest extends InstallerTranslationTest {

  /**
   * Confirms that visiting the installer does not break things post-install.
   */
  public function testVisitInstallerAlreadyInstalled(): void {
    $this->drupalGet('/core/install.php');
    $this->assertSession()->pageTextContains('Drupal already installed');

    // The installer is still blocked when the stored install task points to a
    // task before the configure form, such as a pending translation import.
    \Drupal::state()->set('install_task', 'install_import_translations');
    $this->drupalGet('/core/install.php');
    $this->assertSession()->pageTextContains('Drupal already installed');

    // Also confirm for other pre-configuration tasks when the site is already
    // installed.
    \Drupal::state()->set('install_task', 'install_profile_modules');
    $this->drupalGet('/core/install.php');
    $this->assertSession()->pageTextContains('Drupal already installed');
  }

}
