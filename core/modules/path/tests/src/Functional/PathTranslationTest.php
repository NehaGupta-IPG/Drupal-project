<?php

declare(strict_types=1);

namespace Drupal\Tests\path\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;

/**
 * Tests alias langcode for multilingual nodes.
 *
 * @group path
 */
class PathTranslationTest extends PathTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'path',
    'locale',
    'content_translation',
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

    // Create and log in user.
    $permissions = [
      'access administration pages',
      'administer content translation',
      'administer content types',
      'administer languages',
      'administer url aliases',
      'create content translations',
      'create page content',
      'create url aliases',
      'edit any page content',
      'translate any entity',
    ];
    $user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($user);

    // Enable French language.
    $edit = [
      'predefined_langcode' => 'fr',
    ];
    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm($edit, 'Add language');
    // Languages are cached on many levels, and we need to clear those caches.
    $this->container->get('language_manager')->reset();
  }

  /**
   * Tests the alias langcode for untranslatable node.
   */
  public function testAliasUntranslatableNode(): void {
    // Create a node.
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'langcode' => 'en',
    ]);
    $alias = $this->randomMachineName();

    // Edit the node to set the alias.
    $edit = [
      'path[0][alias]' => '/' . $alias,
    ];
    $this->drupalGet($node->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');

    // Tests that the alias works.
    $this->drupalGet($alias);
    $this->assertSession()->responseContains($node->get('body')->value);

    // Tests that the alias was saved with 'und' langcode.
    $conditions = [
      'path' => '/node/' . $node->id(),
      'alias' => '/' . $alias,
    ];
    $path = $this->loadPathAliasByConditions($conditions);
    $this->assertEquals(LanguageInterface::LANGCODE_NOT_SPECIFIED, $path->language()->getId());
  }

  /**
   * Tests the alias langcode for translatable node.
   */
  public function testAliasLangcode(): void {
    // Tests the langcode for translatable path.
    // It should be set to the node's langcode.
    $english_alias = $this->randomMachineName();
    $french_alias = $this->randomMachineName();
    $this->doTestAliasLangcode(TRUE, $english_alias, $french_alias, 'en', 'fr');

    // Tests the langcode for untranslatable path.
    // It should be set to not specified.
    $english_alias = $this->randomMachineName();
    $not_specified = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    $this->doTestAliasLangcode(FALSE, $english_alias, $english_alias, $not_specified, $not_specified);
  }

  /**
   * Helper method to test aliases' langcode.
   *
   * @param bool $translate_path
   *   Whether the path should be translated.
   * @param string $english_alias
   *   The English alias.
   * @param string $french_alias
   *   The French alias.
   * @param string $expected_en
   *   The expected langcode for English.
   * @param string $expected_fr
   *   The expected langcode for Spanish.
   */
  protected function doTestAliasLangcode(bool $translate_path, string $english_alias, string $french_alias, string $expected_en, string $expected_fr): void {
    // Enable translation for page nodes.
    $edit = [
      'entity_types[node]' => 1,
      'settings[node][page][translatable]' => 1,
      'settings[node][page][fields][path]' => $translate_path,
      'settings[node][page][fields][body]' => 1,
      'settings[node][page][settings][language][language_alterable]' => 1,
    ];
    $this->drupalGet('admin/config/regional/content-language');
    $this->submitForm($edit, 'Save configuration');

    // Create a node.
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $english_node = $this->drupalCreateNode([
      'type' => 'page',
      'langcode' => 'en',
    ]);

    // Edit the node to set the alias.
    $edit_en = [
      'path[0][alias]' => '/' . $english_alias,
    ];
    $this->drupalGet($english_node->toUrl('edit-form'));
    $this->submitForm($edit_en, 'Save');

    // Translate the node into French.
    $edit_fr = [
      'title[0][value]' => $this->randomMachineName(),
      'body[0][value]' => $this->randomMachineName(),
      'path[0][alias]' => '/' . $french_alias,
    ];
    $this->drupalGet(Url::fromRoute('entity.node.content_translation_add', ['node' => $english_node->id(), 'source' => 'en', 'target' => 'fr']));
    $this->submitForm($edit_fr, 'Save (this translation)');

    // Clear the path lookup cache.
    $this->container->get('path_alias.manager')->cacheClear();

    // Ensure the node was created.
    $node_storage->resetCache([$english_node->id()]);
    $english_node = $node_storage->load($english_node->id());
    $this->assertTrue($english_node->hasTranslation('fr'));
    $french_translation = $english_node->getTranslation('fr');

    // Tests that both aliases work.
    $this->drupalGet($edit_en['path[0][alias]']);
    $this->assertSession()->responseContains($english_node->get('body')->value);
    $this->drupalGet('fr' . $edit_fr['path[0][alias]']);
    $this->assertSession()->responseContains($french_translation->get('body')->value);

    // Tests that the English alias was saved with the expected langcode.
    $conditions = [
      'path' => '/node/' . $english_node->id(),
      'alias' => $edit_en['path[0][alias]'],
    ];
    $path_en = $this->loadPathAliasByConditions($conditions);
    $this->assertEquals($expected_en, $path_en->language()->getId());

    // Tests that the French alias was saved with the expected langcode.
    $conditions = [
      'path' => '/node/' . $french_translation->id(),
      'alias' => $edit_fr['path[0][alias]'],
    ];
    $path_fr = $this->loadPathAliasByConditions($conditions);
    $this->assertEquals($expected_fr, $path_fr->language()->getId());
  }

}
