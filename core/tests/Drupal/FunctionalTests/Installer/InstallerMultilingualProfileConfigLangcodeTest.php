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
 * nor English.
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
  }

}
