<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Config;

use Drupal\Core\Entity\UnsupportedEntityOperationException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// cspell:ignore Übersetzter
// cspell:ignore Titel

/**
 * Confirm that language overrides work.
 */
#[Group('config')]
#[RunTestsInSeparateProcesses]
class ConfigLanguageOverrideTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'language',
    'config_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['config_test']);

    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('de')->save();
  }

  /**
   * Tests locale override based on language.
   */
  public function testConfigLanguageOverride(): void {
    // The language module implements a config factory override object that
    // overrides configuration when the Language module is enabled. This test
    // ensures that English overrides work.
    \Drupal::languageManager()->setConfigOverrideLanguage(\Drupal::languageManager()->getLanguage('en'));
    $config = \Drupal::config('config_test.system');
    $this->assertSame('en bar', $config->get('foo'));

    // Ensure that the raw data is not translated.
    $raw = $config->getRawData();
    $this->assertSame('bar', $raw['foo']);

    \Drupal::languageManager()->setConfigOverrideLanguage(\Drupal::languageManager()->getLanguage('fr'));
    $config = \Drupal::config('config_test.system');
    $this->assertSame('fr bar', $config->get('foo'));

    \Drupal::languageManager()->setConfigOverrideLanguage(\Drupal::languageManager()->getLanguage('de'));
    $config = \Drupal::config('config_test.system');
    $this->assertSame('de bar', $config->get('foo'));

    // Test overrides of completely new configuration objects. In normal runtime
    // this should only happen for configuration entities as we should not be
    // creating simple configuration objects on the fly.
    \Drupal::languageManager()
      ->getLanguageConfigOverride('de', 'config_test.new')
      ->set('language', 'override')
      ->save();
    $config = \Drupal::config('config_test.new');
    $this->assertTrue($config->isNew(), 'The configuration object config_test.new is new');
    $this->assertSame('override', $config->get('language'));
    $this->assertNull($config->getOriginal('language', FALSE));

    // Test how overrides react to base configuration changes. Set up some base
    // values.
    \Drupal::configFactory()->getEditable('config_test.foo')
      ->set('value', ['key' => 'original'])
      ->set('label', 'Original')
      // `label` is translatable, hence a `langcode` is required.
      // @see \Drupal\Core\Config\Plugin\Validation\Constraint\LangcodeRequiredIfTranslatableValuesConstraint
      ->set('langcode', 'en')
      ->save();
    \Drupal::languageManager()
      ->getLanguageConfigOverride('de', 'config_test.foo')
      ->set('value', ['key' => 'override'])
      ->set('label', 'Override')
      ->save();
    \Drupal::languageManager()
      ->getLanguageConfigOverride('fr', 'config_test.foo')
      ->set('value', ['key' => 'override'])
      ->save();
    \Drupal::configFactory()->clearStaticCache();
    $config = \Drupal::config('config_test.foo');
    $this->assertSame(['key' => 'override'], $config->get('value'));

    // Ensure renaming the config will rename the override.
    \Drupal::languageManager()->setConfigOverrideLanguage(\Drupal::languageManager()->getLanguage('en'));
    \Drupal::configFactory()->rename('config_test.foo', 'config_test.bar');
    $config = \Drupal::config('config_test.bar');
    $this->assertEquals(['key' => 'original'], $config->get('value'));
    $override = \Drupal::languageManager()->getLanguageConfigOverride('de', 'config_test.foo');
    $this->assertTrue($override->isNew());
    $this->assertNull($override->get('value'));
    $override = \Drupal::languageManager()->getLanguageConfigOverride('de', 'config_test.bar');
    $this->assertFalse($override->isNew());
    $this->assertEquals(['key' => 'override'], $override->get('value'));
    $override = \Drupal::languageManager()->getLanguageConfigOverride('fr', 'config_test.bar');
    $this->assertFalse($override->isNew());
    $this->assertEquals(['key' => 'override'], $override->get('value'));

    // Ensure changing data in the config will update the overrides.
    $config = \Drupal::configFactory()->getEditable('config_test.bar')->clear('value.key')->save();
    $this->assertEquals([], $config->get('value'));
    $override = \Drupal::languageManager()->getLanguageConfigOverride('de', 'config_test.bar');
    $this->assertFalse($override->isNew());
    $this->assertNull($override->get('value'));
    // The French override will become empty and therefore removed.
    $override = \Drupal::languageManager()->getLanguageConfigOverride('fr', 'config_test.bar');
    $this->assertTrue($override->isNew());
    $this->assertNull($override->get('value'));

    // Ensure deleting the config will delete the override.
    \Drupal::configFactory()->getEditable('config_test.bar')->delete();
    $override = \Drupal::languageManager()->getLanguageConfigOverride('de', 'config_test.bar');
    $this->assertTrue($override->isNew());
    $this->assertNull($override->get('value'));
  }

  /**
   * Tests language overrides of config entities.
   */
  public function testConfigEntityLanguageOverrides(): void {
    /** @var \Drupal\language\ConfigurableLanguageManagerInterface $language_manager */
    $language_manager = \Drupal::languageManager();
    $storage = \Drupal::entityTypeManager()->getStorage('config_test');

    // Create a config entity and a language override.
    $config_entity = $storage->create([
      'id' => 'override_test',
      'label' => 'Original language',
    ]);
    $config_entity->save();

    $language_manager->setConfigOverrideLanguage($language_manager->getLanguage('de'));

    // Load the entity while having an active config override, this should work.
    $config_entity = $storage->load('override_test');
    $config_entity->set('label', 'Changed label');
    $config_entity->save();

    $config_entity = $storage->load('override_test');
    $this->assertEquals('Changed label', $config_entity->label());

    $language_manager
      ->getLanguageConfigOverride('de', 'config_test.dynamic.override_test')
      ->set('label', 'Übersetzter Titel')
      ->save();
    \Drupal::configFactory()->reset();

    $config_entity = $storage->load('override_test');
    $this->assertEquals('Übersetzter Titel', $config_entity->label());

    $config_entity->set('label', 'Changed again');
    $this->expectException(UnsupportedEntityOperationException::class);
    $this->expectExceptionMessage('A config entity with config overrides must not be saved. Use \Drupal\Core\Config\Entity\ConfigEntityStorageInterface::loadOverrideFree() to load a non-overridden config entity.');
    $config_entity->save();
  }

}
