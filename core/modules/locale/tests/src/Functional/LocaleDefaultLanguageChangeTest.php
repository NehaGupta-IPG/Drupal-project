<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\Functional;

use Drupal\Core\Language\Language;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests changing the site default language when locale is installed.
 */
#[Group('locale')]
#[RunTestsInSeparateProcesses]
class LocaleDefaultLanguageChangeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['locale', 'locale_test_default_language_content_type'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests cancel and proceed behavior on the confirm step.
   */
  public function testConfirmFlow(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    $langcode = 'xx';
    $edit = [
      'predefined_langcode' => 'custom',
      'langcode' => $langcode,
      'label' => $this->randomMachineName(16),
      'direction' => Language::DIRECTION_LTR,
    ];
    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm($edit, 'Add custom language');

    // Add a site name translation override for our language. While
    // system.site:name is translatable through language overrides, it is not
    // part of the shipped default configuration, so it will not get
    // shuffled around as we change language.
    $original_site_name = $this->config('system.site')->get('name');
    $xx_site_name = 'XX site name';
    \Drupal::languageManager()
      ->getLanguageConfigOverride($langcode, 'system.site')
      ->set('name', $xx_site_name)
      ->save();

    // Attempt to change site default language and weight of the new language,
    // but cancel on the confirm step.
    $this->drupalGet('admin/config/regional/language');
    $initial_weight = \Drupal::entityTypeManager()
      ->getStorage('configurable_language')
      ->load($langcode)
      ->getWeight();
    $this->submitForm([
      'site_default_language' => $langcode,
      "languages[$langcode][weight]" => $initial_weight - 10,
    ], 'Save configuration');
    $this->assertSession()->addressEquals(Url::fromRoute('locale.default_language_confirm'));

    $weight_before_confirm = \Drupal::entityTypeManager()
      ->getStorage('configurable_language')
      ->load($langcode)
      ->getWeight();
    $this->assertSame($initial_weight, $weight_before_confirm);

    $this->clickLink('Cancel');
    $this->assertSession()->addressEquals(Url::fromRoute('entity.configurable_language.collection'));
    $this->assertSame('en', $this->config('system.site')->get('default_langcode'));

    $weight_after_cancel = \Drupal::entityTypeManager()
      ->getStorage('configurable_language')
      ->load($langcode)
      ->getWeight();
    $this->assertSame($initial_weight, $weight_after_cancel);

    // Now test changing the site default language and weight of the new
    // language, and proceed on the confirm step.
    $this->drupalGet('admin/config/regional/language');
    $this->submitForm([
      'site_default_language' => $langcode,
      "languages[$langcode][weight]" => $initial_weight - 10,
    ], 'Save configuration');
    $this->assertSession()->addressEquals(Url::fromRoute('locale.default_language_confirm'));
    $this->submitForm([], 'Proceed');

    $this->rebuildContainer();
    $active_system_site = \Drupal::service('config.storage')->read('system.site');
    $this->assertSame($langcode, $active_system_site['langcode']);
    // Active site name stays the same because this test only applies an
    // override for system.site:name, not a shipped default-config translation
    // update for this key.
    $this->assertSame($original_site_name, $active_system_site['name']);
    $weight_after_confirm = \Drupal::entityTypeManager()
      ->getStorage('configurable_language')
      ->load($langcode)
      ->getWeight();
    $this->assertSame($initial_weight - 10, $weight_after_confirm);

    $this->drupalGet(Url::fromRoute('locale.default_language_confirm'));
    $this->assertSession()->addressEquals(Url::fromRoute('entity.configurable_language.collection'));

    // Add another language and switch default again to verify that the same
    // locale batch path also changes existing default-config langcodes from
    // the previous default language.
    $second_langcode = 'yy';
    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm([
      'predefined_langcode' => 'custom',
      'langcode' => $second_langcode,
      'label' => $this->randomMachineName(16),
      'direction' => Language::DIRECTION_LTR,
    ], 'Add custom language');

    $yy_site_name = 'YY site name';
    \Drupal::languageManager()
      ->getLanguageConfigOverride($second_langcode, 'system.site')
      ->set('name', $yy_site_name)
      ->save();
    $this->assertSame(
      $yy_site_name,
      \Drupal::languageManager()->getLanguageConfigOverride($second_langcode, 'system.site')->get('name')
    );

    $this->drupalGet('admin/config/regional/language');
    $this->submitForm([
      'site_default_language' => $second_langcode,
    ], 'Save configuration');
    $this->assertSession()->addressEquals(Url::fromRoute('locale.default_language_confirm'));
    $this->submitForm([], 'Proceed');

    $this->rebuildContainer();
    $active_system_site = \Drupal::service('config.storage')->read('system.site');
    $this->assertSame($second_langcode, $this->config('system.site')->get('default_langcode'));
    $this->assertSame($second_langcode, $active_system_site['langcode']);
    // Same rationale as above: language-specific override values exist, while
    // raw active storage keeps the original site name.
    $this->assertSame($original_site_name, $active_system_site['name']);
    $this->assertSame(
      $xx_site_name,
      \Drupal::languageManager()->getLanguageConfigOverride($langcode, 'system.site')->get('name')
    );
    $this->assertSame(
      $yy_site_name,
      \Drupal::languageManager()->getLanguageConfigOverride($second_langcode, 'system.site')->get('name')
    );
  }

  /**
   * Tests default-language switch lifecycle across three languages.
   */
  public function testDefaultLanguageSwitchLifecycle(): void {
    $admin_user = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    $first_langcode = 'xx';
    $second_langcode = 'yy';
    $xx_name = 'XX content type name';
    $yy_name = 'YY content type name';
    $content_type_id = 'locale_lifecycle';
    $content_type_config_name = "node.type.$content_type_id";
    $english_active_content_type = \Drupal::service('config.storage')->read($content_type_config_name);

    // Add XX as the first language.
    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm([
      'predefined_langcode' => 'custom',
      'langcode' => $first_langcode,
      'label' => $this->randomMachineName(16),
      'direction' => Language::DIRECTION_LTR,
    ], 'Add custom language');

    // Translate the content type name to XX.
    \Drupal::languageManager()
      ->getLanguageConfigOverride($first_langcode, $content_type_config_name)
      ->set('name', $xx_name)
      ->save();

    // Step 1: change site default language from EN -> XX.
    $this->drupalGet('admin/config/regional/language');
    $this->submitForm([
      'site_default_language' => $first_langcode,
    ], 'Save configuration');
    $this->assertSession()->addressEquals(Url::fromRoute('locale.default_language_confirm'));
    $this->submitForm([], 'Proceed');

    $this->rebuildContainer();
    // Assert that the active config is now changed to the new default 
    // language, and that it has the translated name but other values
    // remain unchanged.
    $active_content_type = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame($first_langcode, $this->config('system.site')->get('default_langcode'));
    $this->assertSame($first_langcode, $active_content_type['langcode']);
    $this->assertSame($xx_name, $active_content_type['name']);
    $this->assertSame($content_type_id, $active_content_type['type']);
    $this->assertArrayHasKey('preview_mode', $active_content_type);
    // Assert that the English source values are now available as an override
    // because we have English as a site language. The override should only 
    // contain the values that are different from the active config.
    $en_override = \Drupal::languageManager()->getLanguageConfigOverride('en', $content_type_config_name)->get();
    $this->assertSame($english_active_content_type['name'], $en_override['name']);
    $this->assertArrayNotHasKey('type', $en_override);
    $this->assertArrayNotHasKey('preview_mode', $en_override);

    // Add second language with its own overrides.
    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm([
      'predefined_langcode' => 'custom',
      'langcode' => $second_langcode,
      'label' => $this->randomMachineName(16),
      'direction' => Language::DIRECTION_LTR,
    ], 'Add custom language');
    \Drupal::languageManager()
      ->getLanguageConfigOverride($second_langcode, $content_type_config_name)
      ->set('name', $yy_name)
      ->save();

    // Step 2: change site default language XX -> YY.
    $this->drupalGet('admin/config/regional/language');
    $this->submitForm([
      'site_default_language' => $second_langcode,
    ], 'Save configuration');
    $this->assertSession()->addressEquals(Url::fromRoute('locale.default_language_confirm'));
    $this->submitForm([], 'Proceed');

    $this->rebuildContainer();
    // Assert that the active config is now changed to the new default 
    // language, and that it has the translated name but other values
    // remain unchanged.
    $active_content_type = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame($second_langcode, $this->config('system.site')->get('default_langcode'));
    $this->assertSame($second_langcode, $active_content_type['langcode']);
    $this->assertSame($yy_name, $active_content_type['name']);
    $this->assertSame($content_type_id, $active_content_type['type']);
    $this->assertArrayHasKey('preview_mode', $active_content_type);
    // Assert that the XX override is now available and only has the values
    // that are different from the active config.
    $xx_override = \Drupal::languageManager()->getLanguageConfigOverride($first_langcode, $content_type_config_name)->get();
    $this->assertSame($xx_name, $xx_override['name']);
    $this->assertArrayNotHasKey('preview_mode', $xx_override);
    // Assert that the English override is still available and only has the values
    // that are different from the active config.
    $en_override = \Drupal::languageManager()->getLanguageConfigOverride('en', $content_type_config_name)->get();
    $this->assertSame($english_active_content_type['name'], $en_override['name']);
    $this->assertArrayNotHasKey('preview_mode', $en_override);
    
    // Step 3: change site default language YY -> EN.
    $this->drupalGet('admin/config/regional/language');
    $this->submitForm([
      'site_default_language' => 'en',
    ], 'Save configuration');
    $this->assertSession()->addressEquals(Url::fromRoute('locale.default_language_confirm'));
    $this->submitForm([], 'Proceed');

    $this->rebuildContainer();
    // Assert that the active config is now changed to the English and that it
    // has the English name but other values remain unchanged.
    $active_content_type = \Drupal::service('config.storage')->read($content_type_config_name);
    $this->assertSame('en', $this->config('system.site')->get('default_langcode'));
    $this->assertSame('en', $active_content_type['langcode']);
    $this->assertSame($english_active_content_type['name'], $active_content_type['name']);
    $this->assertSame($content_type_id, $active_content_type['type']);
    $this->assertArrayHasKey('preview_mode', $active_content_type);
    // Assert that the YY override is now available and only has the values
    // that are different from the active config.
    $yy_override = \Drupal::languageManager()->getLanguageConfigOverride($second_langcode, $content_type_config_name)->get();
    $this->assertSame($yy_name, $yy_override['name']);
    $this->assertArrayNotHasKey('preview_mode', $yy_override);
    // Assert that the XX override is still available and only has the values
    // that are different from the active config.
    $xx_override = \Drupal::languageManager()->getLanguageConfigOverride($first_langcode, $content_type_config_name)->get();
    $this->assertSame($xx_name, $xx_override['name']);
    $this->assertArrayNotHasKey('preview_mode', $xx_override);
    // Assert that the English override no longer exists at all.
    $en_override = \Drupal::languageManager()->getLanguageConfigOverride('en', $content_type_config_name);
    $this->assertTrue($en_override->isNew());
  }

}
