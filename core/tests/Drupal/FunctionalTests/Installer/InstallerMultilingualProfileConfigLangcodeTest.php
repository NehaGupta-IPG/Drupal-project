<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests a profile can ship configuration in a language other than English.
 *
 * The site is installed in English throughout. The profile itself defines
 * German as an additional configured language and ships configuration
 * explicitly labelled 'de' - neither the language chosen for installation
 * nor English. \Drupal\Core\Config\EventSubscriber\
 * ConfigLangcodeInstallerSubscriber must not stop propagation while
 * resolving the installer's own language, or language module's subscriber
 * never gets a chance to vouch for German as a configured language, and
 * this configuration would be wrongly rejected as unsupported.
 *
 * The profile also ships configuration explicitly labelled 'es' (Spanish),
 * but defines no corresponding language - nothing vouches for that one, so
 * it must be rejected the same way as any other unsupported explicit
 * langcode.
 *
 * @see \Drupal\Core\Config\ConfigLangcodeResolveEvent
 */
#[Group('Installer')]
#[RunTestsInSeparateProcesses]
class InstallerMultilingualProfileConfigLangcodeTest extends InstallerTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing_multilingual_config_install';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests configuration shipped in a configured language installs.
   */
  public function testInstalled(): void {
    $this->assertSession()->addressEquals('user/1');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSame('de', $this->config('config_test.dynamic.german_config')->get('langcode'));
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('config_test')->load('german_config'));

    $this->assertNull($this->config('config_test.dynamic.spanish_config')->get('id'));
    $this->assertNull(\Drupal::entityTypeManager()->getStorage('config_test')->load('spanish_config'));
  }

}
