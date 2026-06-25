<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Installer;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for install requirements.
 */
#[Group('Installer')]
#[RunTestsInSeparateProcesses]
class InstallRequirementsTest extends KernelTestBase {

  /**
   * Confirm installer checks requirements in designated classes.
   */
  public function testRequirements(): void {
    require_once 'core/includes/install.inc';

    $this->assertFalse(isset($GLOBALS['module_install_requirements']));
    drupal_check_module('module_install_requirements');
    $this->assertTrue(isset($GLOBALS['module_install_requirements']));
  }

  /**
   * Tests that the installer returns false if module requirements are not met.
   */
  public function testRequirementsFailure(): void {
    require_once 'core/includes/install.inc';
    $this->assertFalse(drupal_check_module('module_install_unmet_requirements'));
  }

  /**
   * Tests existing configuration install checks module requirements.
   */
  public function testExistingConfigInstallRequirementsFailure(): void {
    require_once 'core/includes/install.inc';
    require_once 'core/includes/install.core.inc';
    $sync_directory = $this->siteDirectory . '/config/sync';
    mkdir($sync_directory, 0777, TRUE);
    file_put_contents($sync_directory . '/core.extension.yml', <<<YAML
module:
  module_install_unmet_requirements: 0
theme: {  }
profile: testing
YAML);

    $requirements = install_check_requirements([
      'config_install_path' => $sync_directory,
      'parameters' => [
        'profile' => 'minimal',
      ],
      'settings_verified' => TRUE,
    ]);

    $this->assertUnmetRequirements($requirements);
  }

  /**
   * Tests recipe install checks module requirements.
   */
  public function testRecipeInstallRequirementsFailure(): void {
    require_once 'core/includes/install.inc';
    require_once 'core/includes/install.core.inc';

    $requirements = install_check_requirements([
      'parameters' => [
        'profile' => FALSE,
        'recipe' => 'core/tests/fixtures/recipes/install_unmet_requirements',
      ],
      'settings_verified' => TRUE,
    ]);

    $this->assertUnmetRequirements($requirements);
  }

  /**
   * Asserts the unmet requirements fixture is present.
   *
   * @param mixed[] $requirements
   *   The requirements to check.
   */
  private function assertUnmetRequirements(array $requirements): void {
    $this->assertArrayHasKey('testing_requirements', $requirements);
    $this->assertSame(RequirementSeverity::Error, $requirements['testing_requirements']['severity']);
    $this->assertSame('Testing requirements failed requirements.', (string) $requirements['testing_requirements']['description']);
  }

}
