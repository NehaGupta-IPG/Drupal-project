<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\Traits\Core\Locale\InstallerLanguageTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that installing a theme relabels its shipped English config.
 *
 * When a theme ships config with langcode 'en' (or no langcode at all) and
 * the site's configuration default language is not English, ConfigInstaller
 * must relabel that configuration to the site default while installing it.
 *
 * @see \Drupal\Core\Config\ConfigInstaller::createConfiguration()
 */
#[Group('locale')]
#[RunTestsInSeparateProcesses]
class LocaleThemeInstallTest extends BrowserTestBase {
  use InstallerLanguageTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language', 'locale'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function getInstallLangcode(): string {
    return 'de';
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $admin = $this->drupalCreateUser([
      'administer themes',
      'administer languages',
      'access administration pages',
    ]);
    $this->drupalLogin($admin);
  }

  /**
   * Tests that installing a theme via the UI updates config language.
   *
   * The olivero theme ships core.date_format.olivero_medium with langcode
   * 'en'. With a non-English configuration default language, ConfigInstaller
   * relabels it to that default while installing the theme's configuration.
   */
  public function testThemeInstallRewritesConfigLangcode(): void {
    // Install olivero via the Appearance UI.
    $this->drupalGet('admin/appearance');
    $this->cssSelect('a[title="Install Olivero theme"]')[0]->click();
    // The ThemeController redirects to the batch page; follow its meta-refresh
    // chain to process all batch operations before asserting config state.
    $this->checkForMetaRefresh();
    $this->rebuildContainer();

    // core.date_format.olivero_medium ships with langcode 'en'. ConfigInstaller
    // should have updated it to 'de'.
    $langcode = $this->config('core.date_format.olivero_medium')->get('langcode');
    $this->assertSame('de', $langcode);

    // Ensure that the theme is installed.
    $this->assertSession()->addressEquals('admin/appearance');
    $this->assertSession()->pageTextContains('The Olivero theme has been installed.');
  }

  /**
   * Tests that "install as default" also updates config language.
   */
  public function testThemeSetDefaultRewritesConfigLangcode(): void {
    $this->drupalGet('admin/appearance');
    $this->cssSelect('a[title="Install Olivero as default theme"]')[0]->click();
    $this->checkForMetaRefresh();
    $this->rebuildContainer();
    $langcode = $this->config('core.date_format.olivero_medium')->get('langcode');
    $this->assertSame('de', $langcode);
    $this->assertSession()->addressEquals('admin/appearance');
    $this->assertSession()->pageTextContains('Olivero is now the default theme.');
  }

}
