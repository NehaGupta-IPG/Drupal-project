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
  system: 0
  module_install_unmet_requirements: 0
theme: {  }
profile: testing
YAML);

    $public_directory = $this->siteDirectory . '/public';
    $this->setSetting('file_public_path', $public_directory);
    $this->assertDirectoryDoesNotExist($public_directory);

    $requirements = $this->installCheckRequirements([
      'config_install_path' => $sync_directory,
      'parameters' => [
        'profile' => FALSE,
      ],
      'settings_verified' => TRUE,
    ]);

    $this->assertUnmetRequirements($requirements);
    $this->assertDirectoryExists($public_directory);
  }

  /**
   * Tests existing configuration install checks profile requirements.
   */
  public function testExistingConfigInstallProfileRequirementsFailure(): void {
    require_once 'core/includes/install.inc';
    require_once 'core/includes/install.core.inc';
    require_once 'core/profiles/tests/testing_site_config/testing_site_config.install';
    $sync_directory = $this->siteDirectory . '/config/sync';
    mkdir($sync_directory, 0777, TRUE);
    file_put_contents($sync_directory . '/core.extension.yml', <<<YAML
module: {  }
theme: {  }
profile: testing_site_config
YAML);

    $requirements = $this->installCheckRequirements([
      'config_install_path' => $sync_directory,
      'parameters' => [
        'profile' => 'testing_site_config',
      ],
      'settings_verified' => TRUE,
    ]);

    $this->assertConfigInstallProfileRequirements($requirements);
  }

  /**
   * Tests recipe install checks module requirements.
   */
  public function testRecipeInstallRequirementsFailure(): void {
    require_once 'core/includes/install.inc';
    require_once 'core/includes/install.core.inc';

    $public_directory = $this->siteDirectory . '/public';
    $this->setSetting('file_public_path', $public_directory);
    $this->assertDirectoryDoesNotExist($public_directory);

    $requirements = $this->installCheckRequirements([
      'parameters' => [
        'profile' => FALSE,
        'recipe' => 'core/tests/fixtures/recipes/install_unmet_requirements',
      ],
      'settings_verified' => TRUE,
    ]);

    $this->assertUnmetRequirements($requirements);
    $this->assertDirectoryExists($public_directory);
  }

  /**
   * Tests invalid recipes are ignored during requirement checks.
   */
  public function testInvalidRecipeRequirementsAreIgnored(): void {
    require_once 'core/includes/install.inc';

    $this->assertSame([], drupal_check_recipe($this->siteDirectory . '/invalid'));
  }

  /**
   * Checks install requirements using an installer-like global state.
   *
   * @param mixed[] $install_state
   *   The install state.
   *
   * @return mixed[]
   *   The requirements.
   */
  private function installCheckRequirements(array $install_state): array {
    $install_state += ['interactive' => FALSE];
    $GLOBALS['install_state'] = $install_state;
    return install_check_requirements($install_state);
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

  /**
   * Asserts config install profile requirements fixture is present.
   *
   * @param mixed[] $requirements
   *   The requirements to check.
   */
  private function assertConfigInstallProfileRequirements(array $requirements): void {
    $this->assertArrayHasKey('config_install', $requirements);
    $this->assertSame(RequirementSeverity::Error, $requirements['config_install']['severity']);
    $this->assertSame('testing_site_config', $requirements['config_install']['value']);
  }

}
