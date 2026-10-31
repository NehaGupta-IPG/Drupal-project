<?php

declare(strict_types=1);

namespace Drupal\BuildTests;

use Drupal\BuildTests\Framework\BuildTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[Group('Installer')]
#[PreserveGlobalState(FALSE)]
#[RequiresPhpExtension('pdo_sqlite')]
#[RunTestsInSeparateProcesses]
final class InstallerCustomizationTest extends BuildTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->copyCodebase();
    // Add the installer customization package to the copied codebase.
    $path = $this->getDrupalRoot() . '/core/tests/fixtures/installer_alteration';
    $this->executeCommand("composer config repositories.installer-alteration path $path");
    $this->assertCommandSuccessful();
    $this->executeCommand('composer require drupal/installer-alter:@dev');
    $this->assertCommandSuccessful();

    // Start the web server so we can visit the installer.
    $this->standUpServer();
  }

  /**
   * Tests getting install-time services from a specific package.
   */
  public function testInstallerServices(): void {
    // Configure the codebase to use the install.services.yml provided by the
    // alterations package.
    $this->executeCommand('composer config extra.drupal-installer --json \'{"service-provider":"Drupal\\\\CustomInstaller\\\\InstallerServiceProvider"}\'');
    $this->assertCommandSuccessful();

    $this->visit();
    $assert_session = $this->getMink()->assertSession();
    $assert_session->pageTextContains('Choose language | Drupal');
  }

}
