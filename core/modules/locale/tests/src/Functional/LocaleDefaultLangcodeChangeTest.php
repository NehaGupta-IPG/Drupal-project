<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\locale\LocaleConfigManager;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that changing the site default language retranslates configuration.
 *
 * @see \Drupal\language\DefaultLangcodeConfigUpdater
 * @see \Drupal\locale\EventSubscriber\LanguageDefaultLangcodeChangeSubscriber
 */
#[Group('locale')]
#[RunTestsInSeparateProcesses]
class LocaleDefaultLangcodeChangeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language', 'locale', 'locale_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests config is relabelled and retranslated on a default language change.
   */
  public function testDefaultLanguageChangeRetranslatesConfig(): void {
    // locale_test.translation ships with langcode 'en' and English text.
    $this->assertSame('en', $this->config('locale_test.translation')->get('langcode'));
    $this->assertSame('English test', $this->config('locale_test.translation')->get('test'));

    ConfigurableLanguage::createFromLangcode('fr')->save();

    // Seed a locale string translation the way importing a .po file would.
    \Drupal::service(LocaleConfigManager::class)
      ->getStringTranslation('locale_test.translation', 'fr', 'English test', '')
      ->setString('French test')
      ->setCustomized(FALSE)
      ->save();

    $admin_user = $this->drupalCreateUser(['administer languages']);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => 'fr'], 'Save configuration');

    // The default-langcode-change batch relabels the configuration to the
    // new default, then locale module's hook into that same batch applies
    // the real translated string - not just the langcode change.
    $this->assertSame('fr', $this->config('locale_test.translation')->get('langcode'));
    $this->assertSame('French test', $this->config('locale_test.translation')->get('test'));
  }

}
