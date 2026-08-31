<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests configuration shipped in the language chosen for installation.
 *
 * Same profile as InstallerMultilingualProfileConfigLangcodeTest, but the
 * site is installed in Spanish this time instead of English. The profile
 * ships no language.entity.es - nothing vouches for Spanish as a
 * configured language - but \Drupal\Core\Config\EventSubscriber\
 * ConfigLangcodeInstallerSubscriber vouches for whatever language was
 * chosen for installation regardless, so configuration explicitly shipped
 * in that language must still install. German, vouched for independently
 * because the profile does define it as a configured language, must still
 * install as well.
 *
 * @see \Drupal\Core\Config\ConfigLangcodeResolveEvent
 */
#[Group('Installer')]
#[RunTestsInSeparateProcesses]
class InstallerMultilingualProfileConfigLangcodeSpanishTest extends InstallerTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing_multilingual_config_install';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $langcode = 'es';

  /**
   * {@inheritdoc}
   */
  protected function setUpLanguage(): void {
    // Place a custom local translation in the translations directory, so the
    // installer finds it locally instead of trying to reach the (in tests,
    // unreachable) translation server.
    mkdir($this->root . '/' . $this->siteDirectory . '/files/translations', 0777, TRUE);
    file_put_contents($this->root . '/' . $this->siteDirectory . '/files/translations/drupal-' . \Drupal::VERSION . '.es.po', <<<PO
      msgid ""
      msgstr ""

      msgid "Save and continue"
      msgstr "Save and continue"
      PO);

    parent::setUpLanguage();
  }

  /**
   * Tests configuration shipped in German and Spanish both installed.
   */
  public function testInstalled(): void {
    $this->assertSession()->addressEquals('user/1');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSame('de', $this->config('config_test.dynamic.german_config')->get('langcode'));
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('config_test')->load('german_config'));

    $this->assertSame('es', $this->config('config_test.dynamic.spanish_config')->get('langcode'));
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('config_test')->load('spanish_config'));
  }

}
