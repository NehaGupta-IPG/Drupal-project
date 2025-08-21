<?php

declare(strict_types=1);

namespace Drupal\Tests\path\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\Tests\path_alias\Traits\PathAliasLanguageFallbackTestTrait;
use Drupal\Tests\Traits\Core\PathAliasTestTrait;

/**
 * Tests loading and storing PathItem path aliases with fallback languages.
 *
 * @group path
 */
class PathItemLanguageFallbackTest extends KernelTestBase {

  use PathAliasTestTrait,
    PathAliasLanguageFallbackTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'path_alias',
    'node',
    'user',
    'system',
    'language',
    'content_translation',
    'path_alias_language_fallback_test',
  ];

  /**
   * The node entity storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected NodeStorageInterface $nodeStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');

    $this->installSchema('node', ['node_access']);

    $node_type = NodeType::create([
      'type' => 'foo',
      'name' => 'Foo',
    ]);
    $node_type->save();

    $this->installConfig(['language']);
    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('de')->save();

    $this->nodeStorage = $this->container->get('entity_type.manager')
      ->getStorage('node');
  }

  /**
   * Reloads the passed node from storage with cache reset.
   */
  protected function reloadNode(NodeInterface $node): NodeInterface {
    $node_id = $node->id();
    $this->nodeStorage->resetCache([$node_id]);
    return $this->nodeStorage->load($node_id);
  }

  /**
   * Asserts that the alias of the node is as expected.
   *
   * The alias is taken from the path field item.
   *
   * @param string|null $expected_alias
   *   The expected alias.
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string|null $translation_langcode
   *   The translation language code to use for alias assertion. Pass NULL to
   *   use the untranslated node.
   */
  protected function assertNodeAlias(
    ?string $expected_alias,
    NodeInterface $node,
    ?string $translation_langcode = NULL,
  ): void {
    if ($translation_langcode !== NULL) {
      $this->assertTrue($node->hasTranslation($translation_langcode));
      $node = $node->getTranslation($translation_langcode);
    }

    $actual_alias = $node->get('path')->alias;
    $this->assertSame($expected_alias, $actual_alias);
  }

  /**
   * Creates a node with translations in specified languages.
   *
   * The node is created without any path aliases.
   *
   * @param string $default_langcode
   *   The language code for the node itself.
   * @param string[] $translation_langcodes
   *   The list of language codes for translations.
   *
   * @return \Drupal\node\NodeInterface
   *   The node that is already saved, but not re-loaded.
   */
  protected function createNodeWithTranslations(
    string $default_langcode,
    array $translation_langcodes,
  ): NodeInterface {
    $node = Node::create([
      'title' => $this->randomString(),
      'langcode' => $default_langcode,
      'type' => 'foo',
    ]);
    $node->save();

    foreach ($translation_langcodes as $langcode) {
      $translation = $node->addTranslation($langcode, [
        'title' => $this->randomString(),
      ]);
      $translation->save();
    }

    return $node;
  }

  /**
   * Returns the internal path of the passed entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get the internal path of.
   *
   * @return string
   *   Returns the internal path of the passed entity.
   */
  protected function getInternalPathOfEntity(EntityInterface $entity): string {
    return '/' . $entity->toUrl()->getInternalPath();
  }

  /**
   * Tests path item on multilingual node with alias in fallback language.
   */
  public function testMultilingualWithAliasInFallbackLanguage(): void {
    // Create a node with two translations, but without any aliases yet.
    $node = $this->createNodeWithTranslations('en', ['fr', 'de']);
    $node = $this->reloadNode($node);
    $this->assertNodeAlias(NULL, $node);
    $this->assertNodeAlias(NULL, $node, 'fr');
    $this->assertNodeAlias(NULL, $node, 'de');

    // Add an alias to the fallback language and make sure it affects the same
    // language only.
    $translation = $node->getTranslation('fr');
    $translation->get('path')->alias = '/foo-fallback';
    $translation->save();
    $node = $this->reloadNode($translation);
    $this->assertNodeAlias(NULL, $node);
    $this->assertNodeAlias('/foo-fallback', $node, 'fr');
    $this->assertNodeAlias(NULL, $node, 'de');

    // Set the fallback language to the language having an alias and make sure
    // that the alias appears in all the translations. This way editors will be
    // able to use it as a default value.
    $this->setPathAliasFallbackLanguage('fr');
    $node = $this->reloadNode($node);
    $this->assertNodeAlias('/foo-fallback', $node);
    $this->assertNodeAlias('/foo-fallback', $node, 'fr');
    $this->assertNodeAlias('/foo-fallback', $node, 'de');

    // Update a translation with the same alias and make sure it neither
    // creates an unnecessary copy of the alias in that language nor changes
    // the one in the fallback language.
    $node = $this->reloadNode($node);
    $translation = $node->getTranslation('de');
    $translation->get('path')->alias = '/foo-fallback';
    $translation->save();
    $this->assertPathAliasExists(
      '/foo-fallback',
      'fr',
      $this->getInternalPathOfEntity($node)
    );
    $this->assertPathAliasNotExists(
      '/foo-fallback',
      'de',
      $this->getInternalPathOfEntity($node)
    );

    // Set a different alias on a different translation and make sure it affects
    // that specific translation only, even if path items have been computed on
    // all the other translations before saving the node.
    $node = $this->reloadNode($node);
    $this->assertNodeAlias('/foo-fallback', $node);
    $this->assertNodeAlias('/foo-fallback', $node, 'fr');
    $this->assertNodeAlias('/foo-fallback', $node, 'de');
    $translation = $node->getTranslation('de');
    $translation->get('path')->alias = '/foo-de';
    $translation->save();
    $node = $this->reloadNode($node);
    $this->assertNodeAlias('/foo-fallback', $node);
    $this->assertNodeAlias('/foo-fallback', $node, 'fr');
    $this->assertNodeAlias('/foo-de', $node, 'de');
  }

  /**
   * Tests a multilingual node with an alias in non-specified language.
   */
  public function testMultilingualNodeWithAliasInNonSpecifiedLanguage(): void {
    // Create a node with two translations, but without any aliases yet.
    $node = $this->createNodeWithTranslations('en', ['fr', 'de']);
    $node = $this->reloadNode($node);
    $this->assertNodeAlias(NULL, $node);
    $this->assertNodeAlias(NULL, $node, 'fr');
    $this->assertNodeAlias(NULL, $node, 'de');

    // Create a path alias in a non-specified language, and make sure it
    // affects all the node translations.
    $this->createPathAlias(
      $this->getInternalPathOfEntity($node),
      '/foo-und',
      LanguageInterface::LANGCODE_NOT_SPECIFIED
    );
    $node = $this->reloadNode($node);
    $this->assertNodeAlias('/foo-und', $node);
    $this->assertNodeAlias('/foo-und', $node, 'fr');
    $this->assertNodeAlias('/foo-und', $node, 'de');

    // Update a translation with the same alias and make sure it neither
    // creates an unnecessary copy of the alias in that language nor changes
    // the one in a non-specified language.
    $node = $this->reloadNode($node);
    $translation = $node->getTranslation('fr');
    $translation->get('path')->alias = '/foo-und';
    $translation->save();
    $this->assertPathAliasExists(
      '/foo-und',
      LanguageInterface::LANGCODE_NOT_SPECIFIED,
      $this->getInternalPathOfEntity($node)
    );
    $this->assertPathAliasNotExists(
      '/foo-und',
      'fr',
      $this->getInternalPathOfEntity($node)
    );

    // Set the alias on a node translation and make sure it affects that
    // specific translation only. The alias in the non-specified language must
    // stay untouched.
    $translation = $node->getTranslation('de');
    $translation->get('path')->alias = '/foo-de';
    $translation->save();
    $node = $this->reloadNode($node);
    $this->assertNodeAlias('/foo-und', $node);
    $this->assertNodeAlias('/foo-und', $node, 'fr');
    $this->assertNodeAlias('/foo-de', $node, 'de');
    $this->assertPathAliasExists(
      '/foo-und',
      LanguageInterface::LANGCODE_NOT_SPECIFIED,
      $this->getInternalPathOfEntity($node)
    );

    // Change the alias on the default translation of the node. It should
    // affect the default language only, keeping the one in the non-specified
    // language untouched.
    $node->get('path')->alias = '/foo-default';
    $node->save();
    $node = $this->reloadNode($node);
    $this->assertNodeAlias('/foo-default', $node);
    $this->assertNodeAlias('/foo-und', $node, 'fr');
    $this->assertNodeAlias('/foo-de', $node, 'de');
    $this->assertPathAliasExists(
      '/foo-und',
      LanguageInterface::LANGCODE_NOT_SPECIFIED,
      $this->getInternalPathOfEntity($node)
    );
  }

  /**
   * Tests a monolingual node with an alias in the non-specified language.
   */
  public function testMonolingualNodeWithAliasInNonSpecifiedLanguage(): void {
    // Create a node without any translations or aliases yet.
    $node = $this->createNodeWithTranslations('en', []);
    $node = $this->reloadNode($node);
    $this->assertNodeAlias(NULL, $node);

    // Create an alias in the non-specified language and make sure it affects
    // the node.
    $this->createPathAlias(
      $this->getInternalPathOfEntity($node),
      '/foo-und',
      LanguageInterface::LANGCODE_NOT_SPECIFIED
    );
    $node = $this->reloadNode($node);
    $this->assertNodeAlias('/foo-und', $node);

    // Update the alias through the path item. Make sure it changes the
    // existing alias without making a copy in the node's language.
    $node->get('path')->alias = '/foo-en';
    $node->save();
    $node = $this->reloadNode($node);
    $this->assertNodeAlias('/foo-en', $node);
    $this->assertPathAliasExists(
      '/foo-en',
      LanguageInterface::LANGCODE_NOT_SPECIFIED,
      $this->getInternalPathOfEntity($node)
    );
    $this->assertPathAliasNotExists(
      '/foo-en',
      'en',
      $this->getInternalPathOfEntity($node)
    );
    $this->assertPathAliasNotExists(
      '/foo-und',
      LanguageInterface::LANGCODE_NOT_SPECIFIED,
      $this->getInternalPathOfEntity($node)
    );
  }

}
