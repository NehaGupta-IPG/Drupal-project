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

  /**
   * Tests the previous default's content survives as a language override.
   *
   * Relabelling only changes the langcode field - the object's values stay
   * whatever they were in the previous default language until (and unless)
   * a translation is applied. Without preserving them first, that previous
   * content would simply be gone the moment a translation is applied, and
   * even without one, would sit mislabelled as the new default forever.
   */
  public function testDefaultLanguageChangePreservesEnglishAsOverride(): void {
    $this->assertSame('en', $this->config('locale_test.translation')->get('langcode'));
    $this->assertSame('English test', $this->config('locale_test.translation')->get('test'));

    ConfigurableLanguage::createFromLangcode('fr')->save();

    $admin_user = $this->drupalCreateUser(['administer languages']);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => 'fr'], 'Save configuration');

    // The site under test's own request already switched over and rebuilt
    // its container, via \Drupal\language\ConfigurableLanguageManager::
    // rebuildServices(). The test runner's container has to be told to do
    // the same explicitly: it never received that request, so left alone it
    // would still be reading 'en' as the default language it was built
    // with - which changes how the config override system decides whether
    // 'en' needs an override storage at all.
    $this->rebuildContainer();

    /** @var \Drupal\language\ConfigurableLanguageManagerInterface $language_manager */
    $language_manager = \Drupal::service('language_manager');
    $english_override = $language_manager->getLanguageConfigOverride('en', 'locale_test.translation');
    $this->assertFalse($english_override->isNew());
    $this->assertSame('English test', $english_override->get('test'));
  }

  /**
   * Tests moving the default language away and back preserves both languages.
   *
   * @see \Drupal\language\DefaultLangcodeConfigUpdater::reconcileConfigLangcodeOperation()
   */
  public function testDefaultLanguageRoundTripPreservesBothLanguages(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();

    \Drupal::service(LocaleConfigManager::class)
      ->getStringTranslation('locale_test.translation', 'fr', 'English test', '')
      ->setString('French test')
      ->setCustomized(FALSE)
      ->save();

    $admin_user = $this->drupalCreateUser(['administer languages']);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => 'fr'], 'Save configuration');
    // See testDefaultLanguageChangePreservesEnglishAsOverride() for why this
    // is needed before anything below asks the config override system a
    // question that depends on the current default language.
    $this->rebuildContainer();
    $this->assertSame('fr', $this->config('locale_test.translation')->get('langcode'));
    $this->assertSame('French test', $this->config('locale_test.translation')->get('test'));

    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => 'en'], 'Save configuration');
    $this->rebuildContainer();

    // Back on English, the active configuration must show the original
    // English text again - not the French text the first switch left
    // behind - because the override that first switch created absorbs back
    // into the active configuration on the way past.
    $this->assertSame('en', $this->config('locale_test.translation')->get('langcode'));
    $this->assertSame('English test', $this->config('locale_test.translation')->get('test'));

    // Moving off French in turn preserves the French translation the same
    // way, so a future switch back to French would not lose it either.
    /** @var \Drupal\language\ConfigurableLanguageManagerInterface $language_manager */
    $language_manager = \Drupal::service('language_manager');
    $french_override = $language_manager->getLanguageConfigOverride('fr', 'locale_test.translation');
    $this->assertFalse($french_override->isNew());
    $this->assertSame('French test', $french_override->get('test'));
  }

}
