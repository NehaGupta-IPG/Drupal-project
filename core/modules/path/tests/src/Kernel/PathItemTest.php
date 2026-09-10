<?php

declare(strict_types=1);

namespace Drupal\Tests\path\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\path\Plugin\Validation\Constraint\PathAliasConstraint;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// cspell:ignore furchtbar
/**
 * Tests loading and storing data using PathItem.
 */
#[Group('path')]
#[RunTestsInSeparateProcesses]
class PathItemTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'path_alias',
    'node',
    'user',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');

    $this->installSchema('node', ['node_access']);

    NodeType::create([
      'type' => 'foo',
      'name' => 'Foo',
    ])->save();

    $this->installConfig(['language']);
    ConfigurableLanguage::createFromLangcode('de')->save();
  }

  /**
   * Tests creating, loading, updating and deleting aliases through PathItem.
   */
  public function testPathItem(): void {
    /** @var \Drupal\path_alias\AliasRepositoryInterface $alias_repository */
    $alias_repository = \Drupal::service('path_alias.repository');

    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    $node = Node::create([
      'title' => 'Testing create()',
      'type' => 'foo',
      'path' => ['alias' => '/foo'],
    ]);
    $this->assertFalse($node->get('path')->isEmpty());
    $this->assertEquals('/foo', $node->get('path')->alias);

    $node->save();
    $this->assertFalse($node->get('path')->isEmpty());
    $this->assertEquals('/foo', $node->get('path')->alias);

    $stored_alias = $alias_repository->lookupBySystemPath('/' . $node->toUrl()->getInternalPath(), $node->language()->getId());
    $this->assertEquals('/foo', $stored_alias['alias']);
    $this->assertEquals($node->language()->getId(), $stored_alias['langcode']);

    $node_storage->resetCache();
    $loaded_node = $node_storage->load($node->id());
    $loaded_node->get('path')->langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    $loaded_node->save();

    $stored_alias = $alias_repository->lookupBySystemPath('/' . $node->toUrl()->getInternalPath(), LanguageInterface::LANGCODE_NOT_SPECIFIED);
    $this->assertEquals('/foo', $stored_alias['alias']);
    $this->assertEquals(LanguageInterface::LANGCODE_NOT_SPECIFIED, $stored_alias['langcode']);
    $stored_aliases = \Drupal::entityTypeManager()->getStorage('path_alias')->loadByProperties([
      'path' => '/' . $node->toUrl()->getInternalPath(),
      'langcode' => $node->language()->getId(),
    ]);
    $this->assertCount(0, $stored_aliases);

    $node_storage->resetCache();

    /** @var \Drupal\node\NodeInterface $loaded_node */
    $loaded_node = $node_storage->load($node->id());
    $this->assertFalse($loaded_node->get('path')->isEmpty());
    $this->assertEquals('/foo', $loaded_node->get('path')->alias);
    $node_storage->resetCache();
    $loaded_node = $node_storage->load($node->id());
    $this->assertEquals('/foo', $loaded_node->get('path')[0]->get('alias')->getValue());

    $node_storage->resetCache();
    $loaded_node = $node_storage->load($node->id());
    $values = $loaded_node->get('path')->getValue();
    $this->assertEquals('/foo', $values[0]['alias']);

    $node_storage->resetCache();
    $loaded_node = $node_storage->load($node->id());
    $this->assertEquals('/foo', $loaded_node->path->alias);

    // Add a translation, verify it is being saved as expected.
    $translation = $loaded_node->addTranslation('de', $loaded_node->toArray());
    $translation->get('path')->alias = '/furchtbar';
    $translation->save();

    // Assert the alias on the English node, the German translation, and the
    // stored aliases.
    $node_storage->resetCache();
    $loaded_node = $node_storage->load($node->id());
    $this->assertEquals('/foo', $loaded_node->path->alias);
    $translation = $loaded_node->getTranslation('de');
    $this->assertEquals('/furchtbar', $translation->path->alias);

    $stored_alias = $alias_repository->lookupBySystemPath('/' . $node->toUrl()->getInternalPath(), $node->language()->getId());
    $this->assertEquals('/foo', $stored_alias['alias']);
    $stored_alias = $alias_repository->lookupBySystemPath('/' . $node->toUrl()->getInternalPath(), $translation->language()->getId());
    $this->assertEquals('/furchtbar', $stored_alias['alias']);

    $node_with_unspecified_alias = Node::create([
      'title' => 'Testing explicit alias langcode',
      'type' => 'foo',
      'langcode' => 'en',
      'path' => [
        'alias' => '/neutral',
        'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      ],
    ]);
    $node_with_unspecified_alias->save();

    $stored_alias = $alias_repository->lookupBySystemPath('/' . $node_with_unspecified_alias->toUrl()->getInternalPath(), LanguageInterface::LANGCODE_NOT_SPECIFIED);
    $this->assertEquals('/neutral', $stored_alias['alias']);
    $this->assertEquals(LanguageInterface::LANGCODE_NOT_SPECIFIED, $stored_alias['langcode']);

    $loaded_node->get('path')->alias = '/bar';
    $this->assertFalse($loaded_node->get('path')->isEmpty());
    $this->assertEquals('/bar', $loaded_node->get('path')->alias);

    $loaded_node->save();
    $this->assertFalse($loaded_node->get('path')->isEmpty());
    $this->assertEquals('/bar', $loaded_node->get('path')->alias);

    $node_storage->resetCache();
    $loaded_node = $node_storage->load($node->id());
    $this->assertFalse($loaded_node->get('path')->isEmpty());
    $this->assertEquals('/bar', $loaded_node->get('path')->alias);

    $loaded_node->get('path')->alias = '/bar';
    $this->assertFalse($loaded_node->get('path')->isEmpty());
    $this->assertEquals('/bar', $loaded_node->get('path')->alias);

    $loaded_node->save();
    $this->assertFalse($loaded_node->get('path')->isEmpty());
    $this->assertEquals('/bar', $loaded_node->get('path')->alias);

    $stored_alias = $alias_repository->lookupBySystemPath('/' . $node->toUrl()->getInternalPath(), $node->language()->getId());
    $this->assertEquals('/bar', $stored_alias['alias']);

    $old_alias = $alias_repository->lookupByAlias('/foo', $node->language()->getId());
    $this->assertNull($old_alias);

    // Reload the node to make sure that it is possible to set a value
    // immediately after loading.
    $node_storage->resetCache();
    $loaded_node = $node_storage->load($node->id());
    $loaded_node->get('path')->alias = '/foobar';
    $loaded_node->save();

    $node_storage->resetCache();
    $loaded_node = $node_storage->load($node->id());
    $this->assertFalse($loaded_node->get('path')->isEmpty());
    $this->assertEquals('/foobar', $loaded_node->get('path')->alias);
    $stored_alias = $alias_repository->lookupBySystemPath('/' . $node->toUrl()->getInternalPath(), $node->language()->getId());
    $this->assertEquals('/foobar', $stored_alias['alias']);

    $old_alias = $alias_repository->lookupByAlias('/bar', $node->language()->getId());
    $this->assertNull($old_alias);

    $loaded_node->get('path')->alias = '';
    $this->assertEquals('', $loaded_node->get('path')->alias);

    $loaded_node->save();

    $stored_alias = $alias_repository->lookupBySystemPath('/' . $node->toUrl()->getInternalPath(), $node->language()->getId());
    $this->assertNull($stored_alias);

    // Check that reading, updating and reading the computed alias again in the
    // same request works without clearing any caches in between.
    $loaded_node = $node_storage->load($node->id());
    $loaded_node->get('path')->alias = '/foo';
    $loaded_node->save();

    $this->assertFalse($loaded_node->get('path')->isEmpty());
    $this->assertEquals('/foo', $loaded_node->get('path')->alias);
    $stored_alias = $alias_repository->lookupBySystemPath('/' . $node->toUrl()->getInternalPath(), $node->language()->getId());
    $this->assertEquals('/foo', $stored_alias['alias']);

    $loaded_node->get('path')->alias = '/foobar';
    $loaded_node->save();

    $this->assertFalse($loaded_node->get('path')->isEmpty());
    $this->assertEquals('/foobar', $loaded_node->get('path')->alias);
    $stored_alias = $alias_repository->lookupBySystemPath('/' . $node->toUrl()->getInternalPath(), $node->language()->getId());
    $this->assertEquals('/foobar', $stored_alias['alias']);

    // Check that \Drupal\Core\Field\FieldItemList::equals() for the path field
    // type.
    $node = Node::create([
      'title' => $this->randomString(),
      'type' => 'foo',
      'path' => ['alias' => '/foo'],
    ]);
    $second_node = Node::create([
      'title' => $this->randomString(),
      'type' => 'foo',
      'path' => ['alias' => '/foo'],
    ]);
    $this->assertTrue($node->get('path')->equals($second_node->get('path')));

    // Change the alias for the second node to a different one and try again.
    $second_node->get('path')->alias = '/foobar';
    $this->assertFalse($node->get('path')->equals($second_node->get('path')));

    // Test the generateSampleValue() method.
    $node = Node::create([
      'title' => $this->randomString(),
      'type' => 'foo',
      'path' => ['alias' => '/foo'],
    ]);
    $node->save();
    $path_field = $node->get('path');
    $path_field->generateSampleItems();
    $node->save();
    $this->assertStringStartsWith('/', $node->get('path')->alias);
  }

  /**
   * Tests that a supplied pid cannot target another entity's path alias.
   */
  public function testCrossEntityAliasProtection(): void {
    $alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');

    // A first node with its own alias.
    $first_node = Node::create([
      'title' => 'First',
      'type' => 'foo',
      'path' => ['alias' => '/first-page'],
    ]);
    $first_node->save();
    $first_internal_path = '/' . $first_node->toUrl()->getInternalPath();
    /** @var \Drupal\path_alias\PathAliasInterface $first_alias */
    $first_alias = current($alias_storage->loadByProperties(['path' => $first_internal_path]));
    $first_pid = (int) $first_alias->id();

    // A second, unrelated node.
    $second_node = Node::create([
      'title' => 'Second',
      'type' => 'foo',
      'path' => ['alias' => '/second-page'],
    ]);
    $second_node->save();

    // Point the second node's pid at the first node's alias and change the
    // alias value. The first node's alias must remain untouched, and a new
    // alias is created for the second node instead.
    $second_node->get('path')->alias = '/second-alias';
    $second_node->get('path')->pid = $first_pid;
    $second_node->save();

    $alias_storage->resetCache();
    $reloaded_first_alias = $alias_storage->load($first_pid);
    $this->assertEquals('/first-page', $reloaded_first_alias->getAlias());
    $this->assertEquals($first_internal_path, $reloaded_first_alias->getPath());
    // The second node's alias points to the second node, not the first.
    $second_alias = current($alias_storage->loadByProperties([
      'path' => '/' . $second_node->toUrl()->getInternalPath(),
      'alias' => '/second-alias',
    ]));
    $this->assertNotFalse($second_alias);
    $this->assertNotEquals($first_pid, (int) $second_alias->id());

    // An empty alias combined with the first node's pid must not delete the
    // first node's alias.
    $second_node->get('path')->alias = '';
    $second_node->get('path')->pid = $first_pid;
    $second_node->save();

    $alias_storage->resetCache();
    $this->assertNotNull($alias_storage->load($first_pid));
    $this->assertEquals('/first-page', $alias_storage->load($first_pid)->getAlias());

    // A newly created entity supplying another entity's pid must not touch
    // that alias; a fresh alias is minted for the new entity instead.
    $third_node = Node::create([
      'title' => 'Third',
      'type' => 'foo',
      'path' => ['alias' => '/third-alias', 'pid' => $first_pid],
    ]);
    $third_node->save();

    $alias_storage->resetCache();
    $this->assertEquals('/first-page', $alias_storage->load($first_pid)->getAlias());
    $third_alias = current($alias_storage->loadByProperties([
      'path' => '/' . $third_node->toUrl()->getInternalPath(),
      'alias' => '/third-alias',
    ]));
    $this->assertNotFalse($third_alias);
    $this->assertNotEquals($first_pid, (int) $third_alias->id());

    // The validation constraint rejects a mismatched pid outright, giving
    // JSON:API and other validated write paths a proper violation.
    $pid_message = (string) (new PathAliasConstraint())->pidMessage;
    $second_node->get('path')->alias = '/second-alias-again';
    $second_node->get('path')->pid = $first_pid;
    $violations = $second_node->get('path')->validate();
    $this->assertGreaterThan(0, $violations->count());
    $messages = array_map(
      fn ($violation) => (string) $violation->getMessage(),
      iterator_to_array($violations),
    );
    $this->assertContains($pid_message, $messages);

    // A matching pid (the entity's own alias) validates and saves normally.
    $second_node->get('path')->alias = '/legitimate';
    $second_node->get('path')->pid = (int) $second_alias->id();
    $this->assertEquals(0, $second_node->get('path')->validate()->count());
    $second_node->save();
    $alias_storage->resetCache();
    $this->assertEquals('/legitimate', $alias_storage->load($second_alias->id())->getAlias());
  }

}
