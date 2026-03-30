<?php

namespace Drupal\Tests\field\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Confirms the config language after rebuilding entity displays.
 */
#[Group('field')]
#[RunTestsInSeparateProcesses]
class FieldRebuildEntityDisplayTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'field_ui',
    'text',
    'user',
    'language',
    'config',
    'locale',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('locale.settings')
      ->set('translation.import_enabled', TRUE)
      ->set('translation.use_source', LOCALE_TRANSLATION_USE_SOURCE_LOCAL)
      ->save();

    $admin_user = $this->drupalCreateUser([
      'administer modules',
      'access administration pages',
      'administer site configuration',
      'administer content types',
      'administer node fields',
      'administer languages',
      'translate interface',
    ]);

    $admin_user->set('preferred_admin_langcode', 'de')->save();
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm([
      'predefined_langcode' => 'de',
    ], 'Add language');

    $this->drupalGet('admin/config/regional/language/detection');
    $this->submitForm([
      'language_interface[enabled][language-url]' => TRUE,
      'language_interface[enabled][language-selected]' => TRUE,
    ], 'Save settings');

    $this->drupalGet('admin/config/regional/language/detection/url');
    $this->submitForm([
      'language_negotiation_url_part' => 'path_prefix',
      'prefix[en]' => 'en',
      'prefix[de]' => 'de',
    ], 'Save configuration');
  }

  /**
   * Verifies config and overrides keep the correct language values.
   */
  public function testEntityDisplayRebuild(): void {
    $config_name = 'core.entity_form_display.node.field_rebuild_a_test.default';

    // Install module A from a German URL.
    $this->getSession()->visit($this->getAbsoluteUrl('/de/admin/modules'));
    $this->submitForm([
      'modules[field_rebuild_a_test][enable]' => TRUE,
    ], 'Install');

    $this->assertSession()->pageTextContains('has been installed');
    $this->assertSession()->pageTextContains('translation file imported');

    $this->rebuildContainer();

    $this->assertTrue(
      $this->container->get('module_handler')->moduleExists('field_rebuild_a_test'),
      'Field rebuild module was not enabled.'
    );

    // Default config: no overrides applied.
    $config = $this->config($config_name);
    $default_data = $config->getOriginal('', FALSE);
    $this->assertSame(
      "Slytherin's distinguished scissors",
      $default_data['third_party_settings']['field_rebuild_a_test']['label']
    );

    // German language override only.
    $de_override = $this->container
      ->get('language_manager')
      ->getLanguageConfigOverride('de', $config_name)
      ->get();

    $this->assertSame(
      'Hachtendrachten wurgen krachten',
      $de_override['third_party_settings']['field_rebuild_a_test']['label']
    );

    // Install module B from a German URL.
    $this->getSession()->visit($this->getAbsoluteUrl('/de/admin/modules'));
    $this->submitForm([
      'modules[field_rebuild_b_test][enable]' => TRUE,
    ], 'Install');

    $this->assertSession()->pageTextContains('has been installed');

    $this->rebuildContainer();

    // Re-fetch after rebuild/install.
    $config = $this->config($config_name);
    $default_data = $config->getOriginal('', FALSE);

    $this->assertSame(
      "Slytherin's distinguished scissors",
      $default_data['third_party_settings']['field_rebuild_a_test']['label'],
      'The default config no longer contains the English value.'
    );

    $de_override = $this->container
      ->get('language_manager')
      ->getLanguageConfigOverride('de', $config_name)
      ->get();

    $this->assertSame(
      'Hachtendrachten wurgen krachten',
      $de_override['third_party_settings']['field_rebuild_a_test']['label'],
      'The German language override no longer contains the German value.'
    );
  }

}
