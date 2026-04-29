<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationSelected;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\locale\Gettext;
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

    $config_name = 'core.entity_form_display.node.field_rebuild_a_test.default';
    $config = $this->config($config_name);
    $config->delete();

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

    ConfigurableLanguage::createFromLangcode('de')->save();

    $this->config('language.types')
      ->set('negotiation.language_interface.enabled', [
        LanguageNegotiationUrl::METHOD_ID => -10,
        LanguageNegotiationSelected::METHOD_ID => 12,
      ])
      ->save();

    $this->config('language.negotiation')
      ->set('url.source', LanguageNegotiationUrl::CONFIG_PATH_PREFIX)
      ->set('url.prefixes.en', 'en')
      ->set('url.prefixes.de', 'de')
      ->save();

    $this->container
      ->get('module_installer')
      ->install(['field_rebuild_a_test']);

    $this->container = \Drupal::getContainer();

    $module_path = $this->container
      ->get('extension.list.module')
      ->getPath('field_rebuild_a_test');

    $file = (object) [
      'uri' => $module_path . '/translations/field_rebuild_a_test.de.po',
      'langcode' => 'de',
    ];

    Gettext::fileToDatabase(
      $file,
      [
        'langcode' => 'de',
        'overwrite_options' => [
          'not_customized' => TRUE,
          'customized' => TRUE,
        ],
        'customized' => LOCALE_NOT_CUSTOMIZED,
      ]
    );

    $this->container = \Drupal::getContainer();

    $this->container
      ->get('locale.config_manager')
      ->updateConfigTranslations([
        'core.entity_form_display.node.field_rebuild_a_test.default',
      ], ['de']);
  }

  /**
   * Verifies config and overrides keep the correct language values.
   */
  public function testEntityDisplayRebuild(): void {
    $config_name = 'core.entity_form_display.node.field_rebuild_a_test.default';

    $this->assertTrue($this->container->get('module_handler')->moduleExists('field_rebuild_a_test'));

    $config = $this->config($config_name);
    $default_data = $config->getOriginal('', FALSE);

    $this->assertSame(
      "Field Rebuild Entity Display Test - English Label",
      $default_data['third_party_settings']['language']['label']
    );

    $de_override = $this->container
      ->get('language_manager')
      ->getLanguageConfigOverride('de', $config_name)
      ->get();

    $this->assertSame(
      'Field Rebuild Entity Display Test - German Label',
      $de_override['third_party_settings']['language']['label']
    );

    $this->getSession()->visit($this->getAbsoluteUrl('/de/admin/modules'));
    $this->submitForm([
      'modules[field_rebuild_b_test][enable]' => TRUE,
    ], 'Install');

    $this->assertSession()->pageTextContains('has been installed');

    $this->rebuildContainer();

    $config = $this->config($config_name);
    $default_data = $config->getOriginal('', FALSE);

    $this->assertSame(
      "Field Rebuild Entity Display Test - English Label",
      $default_data['third_party_settings']['language']['label']
    );

    $de_override = $this->container
      ->get('language_manager')
      ->getLanguageConfigOverride('de', $config_name)
      ->get();

    $this->assertSame(
      'Field Rebuild Entity Display Test - German Label',
      $de_override['third_party_settings']['language']['label'],
    );
  }

}
