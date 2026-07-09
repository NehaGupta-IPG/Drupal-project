<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use Drupal\Component\Serialization\Yaml;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// cspell:ignore arrr enregistrer
/**
 * Tests that install-time config import runs the storage transform pipeline.
 *
 * Installing from existing configuration must run the config import
 * transformation pipeline, exactly like a runtime configuration import, so that
 * subscribers to the config import transform event (for example config_ignore,
 * or the transformer honouring config_exclude_modules) are not silently skipped
 * during installation.
 *
 * @see install_config_import_batch()
 * @see install_config_revert_install_changes()
 */
#[Group('Installer')]
#[RunTestsInSeparateProcesses]
class InstallerConfigImportTransformTest extends InstallerConfigDirectoryTestBase {

  /**
   * {@inheritdoc}
   */
  protected function prepareEnvironment(): void {
    parent::prepareEnvironment();

    // Place a custom local translation in the translations directory. The
    // configuration is from a site installed in French, so the installer
    // detects that the site must be installed in French.
    mkdir($this->root . '/' . $this->siteDirectory . '/files/translations', 0777, TRUE);
    file_put_contents($this->root . '/' . $this->siteDirectory . '/files/translations/drupal-' . \Drupal::VERSION . '.fr.po', "msgid \"\"\nmsgstr \"\"\nmsgid \"Save and continue\"\nmsgstr \"Enregistrer et continuer\"");
    $this->translations['Save and continue'] = 'Enregistrer et continuer';

    // Enable the config storage transformer test module in the configuration
    // being installed from, so that a config import transform subscriber is
    // active during installation. Its onImportTransform() appends " Arrr" to
    // the site name; this is only reflected in the installed site if the
    // install-time config import runs the transformation pipeline.
    $sync = $this->siteDirectory . '/profiles/' . $this->profile . '/config/sync';
    $core_extension = Yaml::decode(file_get_contents("$sync/core.extension.yml"));
    $core_extension['module']['config'] = 0;
    $core_extension['module']['config_transformer_test'] = 0;
    $core_extension['module'] = module_config_sort($core_extension['module']);
    file_put_contents("$sync/core.extension.yml", Yaml::encode($core_extension));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpLanguage(): void {
    // This step gets skipped because the config we're installing from was
    // created from a site installed in French, and the installer automatically
    // detects that.
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfigLocation(): string {
    return __DIR__ . '/../../../fixtures/config_install/testing_config_install';
  }

  /**
   * Confirms the config import transformation pipeline ran during install.
   */
  public function testConfigImportTransform(): void {
    // config_transformer_test::onImportTransform() appends " Arrr" to the site
    // name. Its presence proves install_config_revert_install_changes() ran the
    // config import transformation pipeline: without it the raw committed site
    // name would be imported verbatim.
    $this->assertStringEndsWith(' Arrr', $this->config('system.site')->get('name'));
  }

}
