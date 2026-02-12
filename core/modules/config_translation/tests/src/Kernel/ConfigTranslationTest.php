<?php

declare(strict_types=1);

namespace Drupal\Tests\config_translation\Kernel;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that the install/optional configuration gets translated correctly.
 *
 * @group config_translation
 */
class ConfigTranslationTest extends KernelTestBase {

  /**
   * A list of modules to install for this test.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'language',
    'locale',
    'block',
  ];

  /**
   * Tests optional configuration translation.
   */
  public function testInstallConfigTranslate(): void {
    $this->installConfig(['language']);
    $locale_tables = [
      'locales_source',
      'locales_target',
      'locales_location',
    ];
    $this->installSchema('locale', $locale_tables);
    $language = ConfigurableLanguage::createFromLangcode('fr');
    $language->save();
    \Drupal::service('module_installer')->install(['config_install_optional_test']);
    $this->installConfig(['config_install_optional_test']);
    // Check, if 'config_install_optional_test.settings' has proper translation.
    $config_translation = \Drupal::languageManager()->getLanguageConfigOverride('fr', 'config_install_optional_test.settings');
    $this->assertTrue($config_translation->get('data.item') == 'Item (fr)');
    $this->assertTrue($config_translation->get('label') == 'Label (fr)');
    $this->assertTrue($config_translation->get('text') == 'Text (fr)');
  }

  /**
   * Tests optional configuration translation.
   */
  public function testOptionalConfigTranslate(): void {
    $this->installConfig(['language']);
    $locale_tables = [
      'locales_source',
      'locales_target',
      'locales_location',
    ];
    $this->installSchema('locale', $locale_tables);
    \Drupal::service('theme_installer')->install(['stark']);
    $language = ConfigurableLanguage::createFromLangcode('fr');
    $language->save();
    \Drupal::service('module_installer')->install(['config_install_optional_test']);
    $this->installConfig(['config_install_optional_test']);
    // Check, if block 'test_translate' has proper translation.
    $config_translation = \Drupal::languageManager()->getLanguageConfigOverride('fr', 'block.block.test_translate');
    $this->assertTrue($config_translation->get('settings.label') == 'Title (fr)');
    // Check, if block with missing dependency has empty config.
    $config_translation = \Drupal::languageManager()->getLanguageConfigOverride('fr', 'block.block.test_translate_unmet');
    $this->assertTrue(empty($config_translation->getRawData()));
  }

}
