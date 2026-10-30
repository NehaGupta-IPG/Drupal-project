<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_link_content\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the menu link content untranslated.
 */
#[Group('Menu')]
#[RunTestsInSeparateProcesses]
class MenuLinkContentUntranslatedTest extends BrowserTestBase {

  /**
   * Admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $admin;

  /**
   * Node one.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node1;
  /**
   * Node two.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node2;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
    'menu_link_content',
    'menu_ui',
    'block',
    'node',
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
    $this->admin = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($this->admin);
    // Add menu block.
    $this->drupalPlaceBlock('system_menu_block:main');

    // Create FR.
    $this->drupalGet('/admin/config/regional/language/add');
    $this->submitForm([
      'predefined_langcode' => 'fr',
    ], 'Add language');

    // Set prefixes to en and fr.
    $this->drupalGet('/admin/config/regional/language/detection/url');
    $this->submitForm([
      'prefix[en]' => 'en',
      'prefix[fr]' => 'fr',
    ], 'Save configuration');

    // Enable URL language detection.
    $this->drupalGet('/admin/config/regional/language/detection');
    $this->submitForm([
      'language_interface[enabled][language-url]' => TRUE,
      'language_content[configurable]' => TRUE,
      'language_content[enabled][language-url]' => TRUE,
    ], 'Save settings');

    // Create content type.
    $this->createContentType([
      'name' => 'Page',
      'type' => 'page',
      'language_configuration[content_translation]' => TRUE,
      'language_configuration[language_alterable]' => TRUE,
    ]);

    // Enable content translation.
    $this->drupalGet('/admin/config/regional/content-language');
    $this->submitForm([
      'entity_types[node]' => TRUE,
      'settings[node][page][translatable]' => TRUE,
      'settings[node][page][fields][title]' => TRUE,
      'entity_types[menu_link_content]' => TRUE,
      'settings[menu_link_content][menu_link_content][translatable]' => TRUE,
      'settings[menu_link_content][menu_link_content][fields][title]' => TRUE,
    ], 'Save');

    // Create nodes.
    $this->node1 = $this->createNode([
      'title' => 'Node one title',
      'type' => 'page',
      'langcode' => 'en',
    ]);
    $this->node1->save();
    $this->node2 = $this->createNode([
      'title' => 'Node two title',
      'type' => 'page',
      'langcode' => 'en',
    ]);
    $this->node2->save();

    // Translate node.
    $this->drupalGet('/fr/node/' . $this->node1->id() . '/translations/add/en/fr');
    $this->submitForm([
      'title[0][value]' => 'Node one title FR',
    ], 'Save');

    // Create menu.
    $this->drupalGet('/en/admin/structure/menu/manage/main/add');
    $this->submitForm([
      'title[0][value]' => 'Menu one',
      'link[0][uri]' => '/node/1',
    ], 'Save');
    $menuId1 = preg_match("/\d+/", $this->getUrl());
    $this->drupalGet('/en/admin/structure/menu/manage/main/add');
    $this->submitForm([
      'title[0][value]' => 'Menu two',
      'link[0][uri]' => '/node/2',
    ], 'Save');

    // Translate menu.
    $this->drupalGet('/fr/admin/structure/menu/item/' . $menuId1 . '/edit/translations/add/en/fr');
    $this->submitForm([
      'title[0][value]' => 'Menu une',
    ], 'Save');

    $this->drupalLogout();
  }

  /**
   * Ensure that the untranslated menu is not visible.
   */
  public function testUntranslatedMenuLink(): void {
    $this->drupalGet('/en/node/' . $this->node1->id());
    $this->assertSession()->linkExists('Menu one');
    $this->assertSession()->linkExists('Menu two');

    $this->drupalGet('/fr/node/' . $this->node1->id());
    $this->assertSession()->linkExists('Menu une');
    $this->assertSession()->linkNotExists('Menu one');
    $this->assertSession()->linkNotExists('Menu two');
  }

}
