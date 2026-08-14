<?php

namespace Drupal\Tests\block\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests block definitions in different languages.
 *
 * @group block
 */
class BlockDefinitionLanguageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'config_translation',
    'menu_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser([
      'administer blocks',
      'translate configuration',
    ]));
    ConfigurableLanguage::createFromLangcode('de')->save();
    $this->drupalGet('admin/structure/menu/manage/admin/translate/de/add');
    $this->submitForm(['translation[config_names][system.menu.admin][label]' => 'Verwaltung'], 'Save translation');
  }

  /**
   * Tests the translation of block derivatives.
   */
  public function testTranslatedDerivatives() {
    $assert_session = $this->assertSession();

    // Clear block definitions caches.
    $this->container->get('plugin.manager.block')->clearCachedDefinitions();

    // cspell:ignore Verwaltung
    // Visit the block listing in the translated language first.
    $this->drupalGet('de/admin/structure/block/library/classy');
    $assert_session->pageTextContains('Verwaltung');
    $assert_session->pageTextNotContains('Administration');
    // Then visit the block listing in the default language.
    $this->drupalGet('admin/structure/block/library/classy');
    $assert_session->pageTextNotContains('Verwaltung');
    $assert_session->pageTextContains('Administration');

    // Clear block definitions caches.
    $this->container->get('plugin.manager.block')->clearCachedDefinitions();

    // Visit the block listing in the default language first.
    $this->drupalGet('admin/structure/block/library/classy');
    $assert_session->pageTextNotContains('Verwaltung');
    $assert_session->pageTextContains('Administration');
    // Then visit the block listing in the translated language.
    $this->drupalGet('de/admin/structure/block/library/classy');
    $assert_session->pageTextContains('Verwaltung');
    $assert_session->pageTextNotContains('Administration');
  }

}
