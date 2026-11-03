<?php

namespace Drupal\FunctionalTests\Installer;

/**
 * Tests actions after installation has finished.
 *
 * @group Installer
 */
class InstallerFinishedTest extends ConfigAfterInstallerTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Verifies installation finished and hook_site_install_finished is called.
   */
  public function testSiteInstallFinished() {
    $this->assertSession()->pageTextContains('Perform action after the installation.');
  }

}
