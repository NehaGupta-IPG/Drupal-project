<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests a profile can ship configuration in another language without a dependency.
 *
 * Unlike \Drupal\FunctionalTests\Installer\
 * InstallerMultilingualProfileConfigLangcodeTest, the profile here does not
 * declare dependencies.config: [language.entity.it] on the shipped
 * config_test.dynamic.italian_config - so dependency-sorted install order
 * cannot be relied on to save language.entity.it before this is reached.
 * \Drupal\Core\Config\ConfigInstaller::createConfiguration() has to already
 * know every language.entity.* this same install run is defining, not just
 * whatever \Drupal\Core\Language\LanguageManagerInterface::getLanguages()
 * happens to already reflect at that point in the loop, or this would be
 * wrongly rejected as unsupported.
 *
 * @see \Drupal\Core\Config\ConfigInstaller::createConfiguration()
 */
#[Group('Installer')]
#[RunTestsInSeparateProcesses]
class InstallerMultilingualProfileConfigLangcodeNoDependencyTest extends InstallerTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing_multilingual_config_install_no_dependency';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests configuration shipped without a declared dependency still installs.
   */
  public function testInstalled(): void {
    $this->assertSession()->addressEquals('user/1');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSame('it', $this->config('config_test.dynamic.italian_config')->get('langcode'));
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('config_test')->load('italian_config'));
  }

}
