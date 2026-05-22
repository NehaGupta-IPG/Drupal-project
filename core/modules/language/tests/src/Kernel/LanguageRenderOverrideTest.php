<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests language render overrides.
 */
#[Group('language')]
#[RunTestsInSeparateProcesses]
class LanguageRenderOverrideTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language'];

  /**
   * Tests the interface language override.
   */
  public function testInterfaceLanguageOverride(): void {
    $this->installConfig(['language']);

    $overrideLanguage = ConfigurableLanguage::createFromLangcode('fr');
    $overrideLanguage->save();

    $settings = Settings::getAll();
    $settings['locale_custom_strings_fr'] = ['' => ['Operations' => 'Actions']];
    new Settings($settings);

    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage();
    $this->assertEquals('en', $currentLanguage->getId());
    $translatedString = \Drupal::translation()->translate('Operations');
    $this->assertEquals('Operations', (string) $translatedString);

    \Drupal::languageManager()->setRenderOverrideLanguage($overrideLanguage);
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage();
    $this->assertEquals('fr', $currentLanguage->getId());
    $translatedString = \Drupal::translation()->translate('Operations');
    $this->assertEquals('Actions', (string) $translatedString);

    \Drupal::languageManager()->setRenderOverrideLanguage(NULL);
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE);
    $this->assertEquals('en', $currentLanguage->getId());
    $translatedString = \Drupal::translation()->translate('Operations');
    $this->assertEquals('Operations', (string) $translatedString);
  }

  /**
   * Tests all types of language render overrides.
   */
  #[DataProvider('languageTypeProvider')]
  public function testLanguageRenderOverride(string $type): void {
    $this->installConfig(['language']);
    $overrideLanguage = ConfigurableLanguage::createFromLangcode('es');
    $overrideLanguage->save();

    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE);
    $this->assertEquals('en', $currentLanguage->getId());
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);
    $this->assertEquals('en', $currentLanguage->getId());
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_URL);
    $this->assertEquals('en', $currentLanguage->getId());

    \Drupal::languageManager()->setRenderOverrideLanguage($overrideLanguage, $type);

    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE);
    $this->assertEquals(LanguageInterface::TYPE_INTERFACE === $type ? 'es' : 'en', $currentLanguage->getId());
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);
    $this->assertEquals(LanguageInterface::TYPE_CONTENT === $type ? 'es' : 'en', $currentLanguage->getId());
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_URL);
    $this->assertEquals(LanguageInterface::TYPE_URL === $type ? 'es' : 'en', $currentLanguage->getId());

    \Drupal::languageManager()->setRenderOverrideLanguage(NULL, $type);

    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE);
    $this->assertEquals('en', $currentLanguage->getId());
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);
    $this->assertEquals('en', $currentLanguage->getId());
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_URL);
    $this->assertEquals('en', $currentLanguage->getId());
  }

  /**
   * Data provider for language types.
   */
  public static function languageTypeProvider(): array {
    return [
      [LanguageInterface::TYPE_INTERFACE],
      [LanguageInterface::TYPE_CONTENT],
      [LanguageInterface::TYPE_URL],
    ];
  }

}
