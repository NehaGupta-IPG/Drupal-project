<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use Drupal\Component\Serialization\Yaml;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies that a local translation source is honored during installation.
 */
#[Group('Installer')]
#[RunTestsInSeparateProcesses]
class InstallerExistingConfigLocalTranslationTest extends InstallerConfigDirectoryTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing_config_install_multilingual';

  /**
   * {@inheritdoc}
   */
  protected function getConfigLocation(): string {
    return __DIR__ . '/../../../fixtures/config_install/multilingual';
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEnvironment(): void {
    parent::prepareEnvironment();

    // The fixture already sets translation.use_source to 'local'. Install in
    // Spanish so that the installer looks for a translation at all.
    $site_config = $this->siteDirectory . '/profiles/' . $this->profile . '/config/sync/system.site.yml';
    $site = Yaml::decode(file_get_contents($site_config));
    $site['langcode'] = 'es';
    $site['default_langcode'] = 'es';
    file_put_contents($site_config, Yaml::encode($site));

    // Create the directory but no translation file in it, so that the server is
    // the only place a translation could come from.
    mkdir($this->root . '/' . $this->siteDirectory . '/files/translations', 0777, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpLanguage(): void {
    // The language is detected from the configuration being installed.
  }

  /**
   * Tests that no translation is fetched when the source is local.
   */
  public function testConfigSync(): void {
    // Guards against the assertions below passing on an English install.
    $this->assertSame('es', $this->config('system.site')->get('default_langcode'));

    // Without a translation on the server the installer stops on the
    // requirements screen, so reaching the front page means it did not ask.
    $this->assertSession()->addressEquals('es/user/1');
    $this->assertSession()->statusCodeEquals(200);

    // A tagged release has one, and there the download succeeds rather than
    // erroring, so only this catches it.
    $this->assertFileDoesNotExist($this->root . '/' . $this->siteDirectory . '/files/translations/drupal-' . \Drupal::VERSION . '.es.po');
  }

}
