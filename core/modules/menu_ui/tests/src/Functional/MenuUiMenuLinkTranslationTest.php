<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_ui\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_translation\Traits\ContentTranslationTestTrait;

/**
 * Tests Menu UI with content translation but not menu link translation.
 *
 * @group menu_ui
 */
class MenuUiMenuLinkTranslationTest extends BrowserTestBase {

  use ContentTranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'language',
    'content_translation',
    'menu_ui',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place menu block and local tasks block.
    $this->drupalPlaceBlock('system_menu_block:main');
    $this->drupalPlaceBlock('local_tasks_block');

    // Create a 'page' content type.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
      'display_submitted' => FALSE,
    ]);

    // Add a second language.
    static::createLanguageFromLangcode('de');

    // Create an account and login.
    $user = $this->drupalCreateUser([
      'administer content translation',
      'administer content types',
      'administer languages',
      'administer menu',
      'administer nodes',
      'administer site configuration',
      'create content translations',
      'create page content',
      'translate any entity',
    ]);
    $this->drupalLogin($user);

    // Enable translation for page nodes.
    $this->enableContentTranslation('node', 'page');
  }

  /**
   * Gets a content entity object by title.
   *
   * @param string $entity_type_id
   *   Id of content entity type of content entity to load.
   * @param string $title
   *   Title of content entity to load.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   First found content entity with given title.
   */
  protected function getContentEntityByTitle($entity_type_id, $title) {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $storage = $entity_type_manager->getStorage($entity_type_id);
    $storage->resetCache();
    $entities = $storage->loadByProperties([
      'title' => $title,
    ]);
    return reset($entities);
  }

  /**
   * Tests menu link language when links are not translatable.
   */
  public function testMenuLinkLanguage(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('menu_link_content');

    // Create a page node in English.
    $this->drupalGet('node/add/page');
    $this->assertSession()->statusCodeEquals(200);
    $edit = [
      'title[0][value]' => 'English page',
      'menu[enabled]' => 1,
      'menu[title]' => 'English menu link',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);

    // Assert that menu link language is English.
    $menu_links = $storage->loadByProperties([
      'title' => 'English menu link',
    ]);
    $menu_link = reset($menu_links);
    $this->assertEquals('en', $menu_link->language()->getId());

    // Assert that menu link is visible with initial title.
    $this->assertSession()->linkExists('English menu link');

    // Change language of page node and title of its menu link.
    $this->clickLink('Translate');
    $this->clickLink('Add');
    $edit = [
      'title[0][value]' => 'German page',
      'menu[title]' => 'German menu link',
    ];
    $this->submitForm($edit, 'Save (this translation)');
    $this->assertSession()->statusCodeEquals(200);

    // Assert that menu link language is still English.
    $menu_links = $storage->loadByProperties([
      'title' => 'German menu link',
    ]);
    $menu_link = reset($menu_links);
    $this->assertEquals('en', $menu_link->language()->getId());
  }

}
