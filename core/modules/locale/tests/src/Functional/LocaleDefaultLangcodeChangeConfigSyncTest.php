<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\locale\LocaleConfigManager;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests changing system.site:default_langcode back via a configuration sync.
 *
 * DefaultConfigLangcodeSubscriber::onConfigSave() skips
 * \Drupal\language\DefaultLangcodeConfigUpdater's retroactive updates while
 * configuration is syncing, on the assumption that an import's source storage
 * is a complete, self-consistent snapshot already responsible for providing
 * correct langcodes for anything it changes itself. This proves that assumption
 * holds: a full site export, changed away from English and changed back by
 * re-importing that same export, ends up exactly as it was exported.
 *
 * @see \Drupal\language\EventSubscriber\DefaultConfigLangcodeSubscriber::onConfigSave()
 * @see \Drupal\Tests\language\Functional\LanguageConfigOverrideImportTest
 */
#[Group('locale')]
#[RunTestsInSeparateProcesses]
class LocaleDefaultLangcodeChangeConfigSyncTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language', 'locale', 'locale_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests a config sync import can change the default language back.
   */
  public function testDefaultLangcodeChangeViaConfigSync(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();

    // Seed a locale string translation the way importing a .po file would.
    \Drupal::service(LocaleConfigManager::class)
      ->getStringTranslation('locale_test.translation', 'fr', 'English test', '')
      ->setString('French test')
      ->setCustomized(FALSE)
      ->save();

    // Export the site while it is still English by default.
    $this->assertSame('en', $this->config('system.site')->get('default_langcode'));
    $this->assertSame('en', $this->config('locale_test.translation')->get('langcode'));
    $this->assertSame('English test', $this->config('locale_test.translation')->get('test'));
    /** @var \Drupal\Core\Config\StorageInterface $sync */
    $sync = \Drupal::service('config.storage.sync');
    $this->copyConfig(\Drupal::service('config.storage'), $sync);

    // Change the default language via the UI - updating and
    // retranslating locale_test.translation to French, as proven separately
    // by LocaleDefaultLangcodeChangeTest.
    $admin_user = $this->drupalCreateUser(['administer languages']);
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/config/regional/language');
    $this->submitForm(['site_default_language' => 'fr'], 'Save configuration');
    $this->assertSame('1', (string) \Drupal::service('database')->select('locales_target', 'ls')->countQuery()->execute()->fetchField(), "There is one translation row in the locales_target table.");

    $this->rebuildContainer();
    $this->assertSame('fr', $this->config('system.site')->get('default_langcode'));
    $this->assertSame('fr', $this->config('locale_test.translation')->get('langcode'));
    $this->assertSame('French test', $this->config('locale_test.translation')->get('test'));

    // Ensure there is a configuration to import.
    $storage_comparer = $this->configImporter()->getStorageComparer();
    $this->assertNotSame($storage_comparer->getEmptyChangelist(), $storage_comparer->getChangelist());

    // Import the pre-change export, changing the default language back to
    // English via configuration sync rather than the UI this time.
    $this->configImporter()->import();
    $this->rebuildContainer();

    // The default language, and every other configuration object the
    // change had touched, are back exactly as exported.
    $this->assertSame('en', $this->config('system.site')->get('default_langcode'));
    $this->assertSame('en', $this->config('locale_test.translation')->get('langcode'));
    $this->assertSame('English test', $this->config('locale_test.translation')->get('test'));
    $this->assertSame('en', \Drupal::languageManager()->getDefaultLanguage()->getId());

    // There should still be a single translation row in the locales_target.
    $this->assertSame('1', (string) \Drupal::service('database')->select('locales_target', 'ls')->countQuery()->execute()->fetchField(), "There is one translation row in the locales_target table.");

    // Ensure all the configuration objects were imported without change.
    $storage_comparer = $this->configImporter()->reset()->getStorageComparer();
    $this->assertSame($storage_comparer->getEmptyChangelist(), $storage_comparer->getChangelist());
  }

}
