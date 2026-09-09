<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\locale\LocaleConfigManager;
use Drupal\locale\StringInterface;
use Drupal\locale\TranslationString;
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
   * The string storage used in this test.
   *
   * @var \Drupal\locale\StringStorageInterface
   */
  protected $stringStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->stringStorage = $this->container->get('locale.storage');
  }

  /**
   * Tests config is updated and retranslated on a default language change.
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

    // The seeded translation must still be exactly the one row it was
    // before the switch, and still not customized.
    $this->assertTranslation('locale_test.translation', 'French test', 'fr', FALSE);
  }

  /**
   * Tests switching to a language with no translation creates no locale row.
   *
   * Updating untranslated shipped text to the new default must not be
   * mistaken, anywhere in the process, for a deliberate translation of it, that
   * would create a customized locale string.
   */
  public function testDefaultLanguageChangeToNonEnglishDoesNotCreateSpuriousTranslation(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $admin_user = $this->drupalCreateUser(['administer languages']);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => 'fr'], 'Save configuration');

    $this->assertSame('fr', $this->config('locale_test.translation')->get('langcode'));
    $this->assertSame('English test', $this->config('locale_test.translation')->get('test'));
    $this->assertNoTranslation('locale_test.translation', 'fr');
  }

  /**
   * Tests switching back to English creates no locale row for English.
   *
   * English is not translatable by default - by design, locale module only
   * deals with sources in English. None of this process may write an
   * "English translation of English" row into locale storage, and the real
   * French translation it passes on the way through must be left exactly as
   * it was.
   */
  public function testDefaultLanguageChangeToEnglishDoesNotCreateEnglishTranslations(): void {
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
    $this->rebuildContainer();
    $this->assertSame('fr', $this->config('locale_test.translation')->get('langcode'));
    $this->assertSame('French test', $this->config('locale_test.translation')->get('test'));

    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => 'en'], 'Save configuration');
    $this->rebuildContainer();

    $this->assertSame('en', $this->config('locale_test.translation')->get('langcode'));
    $this->assertSame('English test', $this->config('locale_test.translation')->get('test'));
    $this->assertNoTranslation('locale_test.translation', 'en');
    $this->assertTranslation('locale_test.translation', 'French test', 'fr', FALSE);
  }

  /**
   * Tests a round trip with English translatable creates no garbage row.
   *
   * With locale.settings:translate_english on, English stops being the one
   * language none of this ever writes to - so this exercises the same
   * relabel-then-save path testDefaultLanguageChangeToEnglishDoesNotCreate
   * EnglishTranslations() found nothing to write for, this time with
   * nothing stopping it from trying. Relabelling text back to English
   * still must not be mistaken for a deliberate translation of it.
   */
  public function testDefaultLanguageChangeWithEnglishTranslatable(): void {
    $this->config('locale.settings')->set('translate_english', TRUE)->save();

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
    $this->rebuildContainer();

    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => 'en'], 'Save configuration');
    $this->rebuildContainer();

    $this->assertSame('en', $this->config('locale_test.translation')->get('langcode'));
    $this->assertSame('English test', $this->config('locale_test.translation')->get('test'));
    $this->assertNoTranslation('locale_test.translation', 'en');
  }

  /**
   * Tests absorbing a pre-existing override creates no locale row.
   *
   * The override here is written directly to storage - the way a config
   * import would arrive, or an override that predates this site ever
   * having locale module enabled - so it never went through locale's own
   * onOverrideChange() and has no corresponding row in locale storage at
   * all. Absorbing it into the active configuration is
   * \Drupal\language\DefaultLangcodeConfigUpdater moving existing content
   * from one place to another, not a fresh act of translation - it must not
   * fabricate a locale row for content locale never had a hand in.
   */
  public function testDefaultLanguageChangeAbsorbingOverrideDoesNotCreateTranslation(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();

    // Write the override straight to storage, bypassing Config and the
    // events a normal ->save() would fire - the same as it never having
    // passed through locale module at all.
    \Drupal::service('config.storage')
      ->createCollection('language.fr')
      ->write('locale_test.translation', ['test' => 'Override text']);
    $this->assertNoTranslation('locale_test.translation', 'fr');

    $admin_user = $this->drupalCreateUser(['administer languages']);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => 'fr'], 'Save configuration');

    $this->assertSame('fr', $this->config('locale_test.translation')->get('langcode'));
    $this->assertSame('Override text', $this->config('locale_test.translation')->get('test'));
    $this->assertNoTranslation('locale_test.translation', 'fr');
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
   * Tests customized content, not just the shipped default, is preserved.
   *
   * The locale_test.translation config ships with 'English test'. If a site
   * builder customized that value before switching the default language, the
   * customization is what must survive as the English override - not the
   * shipped default text, which no longer matches what the site actually
   * says. Preserving the shipped text instead would silently revert this
   * customization the moment the language switch is made.
   */
  public function testDefaultLanguageChangePreservesCustomizedContent(): void {
    $this->config('locale_test.translation')->set('test', 'Customized test')->save();

    ConfigurableLanguage::createFromLangcode('fr')->save();

    $admin_user = $this->drupalCreateUser(['administer languages']);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => 'fr'], 'Save configuration');
    $this->rebuildContainer();

    /** @var \Drupal\language\ConfigurableLanguageManagerInterface $language_manager */
    $language_manager = \Drupal::service('language_manager');
    $english_override = $language_manager->getLanguageConfigOverride('en', 'locale_test.translation');
    $this->assertFalse($english_override->isNew());
    $this->assertSame('Customized test', $english_override->get('test'));
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

    // The round trip must not have left a second, duplicate row behind, nor
    // touched the customized flag on the one row it started with.
    $this->assertTranslation('locale_test.translation', 'French test', 'fr', FALSE);
  }

  /**
   * Asserts no translation exists for a configuration object in a language.
   *
   * @param string $config_name
   *   The configuration name.
   * @param string $langcode
   *   The language code.
   */
  protected function assertNoTranslation(string $config_name, string $langcode): void {
    $strings = $this->stringStorage->getTranslations([
      'type' => 'configuration',
      'name' => $config_name,
      'language' => $langcode,
      'translated' => TRUE,
    ]);
    $this->assertSame([], $strings);
  }

  /**
   * Asserts a translation exists for a configuration object and its status.
   *
   * @param string $config_name
   *   The configuration name.
   * @param string $translation
   *   The expected translation.
   * @param string $langcode
   *   The language code.
   * @param bool $customized
   *   Whether the translation is expected to be marked customized.
   */
  protected function assertTranslation(string $config_name, string $translation, string $langcode, bool $customized): void {
    $strings = $this->stringStorage->getTranslations([
      'type' => 'configuration',
      'name' => $config_name,
      'language' => $langcode,
      'translated' => TRUE,
    ]);
    $this->assertCount(1, $strings);
    $string = reset($strings);
    $this->assertInstanceOf(StringInterface::class, $string);
    /** @var \Drupal\locale\TranslationString $string */
    $this->assertSame($translation, $string->getString());
    $this->assertInstanceOf(TranslationString::class, $string);
    $this->assertEquals($customized, (bool) $string->customized);
  }

}
