<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationContentEntity;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\BrowserTestBase;
use Drupal\Core\Language\LanguageInterface;

/**
 * Test language detection for content entities.
 *
 * @group language
 */
class LanguageContentEntityDetectionConfigurationTest extends BrowserTestBase {

  protected static $modules = [
    'block',
    'language',
    'content_translation',
    'menu_link_content',
    'system',
  ];

  /**
   * Admin user.
   *
   * @var object
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'bartik';

  protected function setUp(): void {
    parent::setUp();

    // Add French language.
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $this->adminUser = $this->drupalCreateUser([
      'administer languages',
      'access administration pages',
      'view the administration theme',
    ]);
    $this->drupalLogin($this->adminUser);

    // Make menu_link_content translatable.
    $config = ContentLanguageSettings::loadByEntityTypeBundle('menu_link_content', 'menu_link_content');
    $config->setDefaultLangcode('site_default')
      ->setLanguageAlterable(TRUE)
      ->save();

    // Make title field translatable.
    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('menu_link_content', 'menu_link_content');
    $field_config = $fields['title']->getConfig('menu_link_content');
    $field_config
      ->setTranslatable(TRUE)
      ->save();

    // Set settings for language detection.
    $config = $this->config('language.types');
    $config->set('configurable', [
      LanguageInterface::TYPE_INTERFACE,
      LanguageInterface::TYPE_CONTENT,
    ]);
    $config->set('negotiation.language_content.enabled', [
      LanguageNegotiationContentEntity::METHOD_ID => 0,
    ]);
    $config->set('negotiation.language_interface.enabled', [
      LanguageNegotiationUrl::METHOD_ID => 0,
    ]);
    $config->save();

    drupal_flush_all_caches();
  }

  /**
   * Test that menu content follows correct translation interface.
   */
  public function testMenuContentLanguageConfiguration() {

    // Add the main menu block.
    $this->drupalPlaceBlock('system_menu_block:main');

    $english_title = 'My menu item EN';
    $french_title = 'My menu item FR';

    // Add a menu item.
    $menu_link_content = MenuLinkContent::create([
      'title' => $english_title,
      'menu_name' => 'main',
      'link' => ['uri' => 'route:<front>'],
    ]);
    $menu_link_content->save();

    // Add French translation for a menu item.
    if ($menu_link_content && !$menu_link_content->hasTranslation('fr')) {
      $menu_link_content->addTranslation('fr', ['title' => $french_title]);
      $menu_link_content->save();
    }

    $user_url = 'user/' . $this->adminUser->id();
    $fr_user_url = 'fr/' . $user_url;

    $this->checkBlockTitleWithLanguageConfiguration($user_url, 'en', $english_title);
    $this->checkBlockTitleWithLanguageConfiguration($fr_user_url, 'en', $english_title);
    $this->checkBlockTitleWithLanguageConfiguration($user_url, 'fr', $french_title);

    // Set configuration that menu has to follow user z interface language.
    $this->drupalGet('admin/config/regional/language/detection/content-entity');
    $this->submitForm(['language_negotiation_menu_lang_type_interface' => 1], 'edit-submit');

    $this->checkBlockTitleWithLanguageConfiguration($user_url, 'en', $english_title);
    $this->checkBlockTitleWithLanguageConfiguration($fr_user_url, 'en', $french_title);
    $this->checkBlockTitleWithLanguageConfiguration($fr_user_url, 'fr', $french_title);

  }

  /**
   * Test that we have correct block title with current language configuration.
   *
   * @param string $url
   *   URL.
   * @param string $content_language
   *   Content language code.
   * @param string $title
   *   Block title.
   */
  protected function checkBlockTitleWithLanguageConfiguration($url, $content_language, $title) {
    \Drupal::service('cache.render')->deleteAll();
    $this->drupalGet($url, ['query' => ['language_content_entity' => $content_language]]);
    $this->assertSession()->linkExists($title);
  }

}
