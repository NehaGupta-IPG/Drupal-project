<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\CancellationHandlerInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests revision-safe account cancellation for core entity types.
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
final class RevisionSafeCancellationHandlerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'language',
    'link',
    'menu_link_content',
    'node',
    'system',
    'taxonomy',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filter', 'node']);

    NodeType::create([
      'type' => 'test',
      'name' => 'Test',
    ])->save();
    Vocabulary::create([
      'vid' => 'test',
      'name' => 'Test',
    ])->save();
    ConfigurableLanguage::createFromLangcode('fr')->save();
  }

  /**
   * Tests reassignment to anonymous without changing the default revision.
   */
  public function testReassignPreservesCurrentRevision(): void {
    $source = User::create(['name' => 'source', 'status' => 1]);
    $source->save();
    $current_editor = User::create(['name' => 'current-editor', 'status' => 1]);
    $current_editor->save();

    $node = Node::create([
      'type' => 'test',
      'title' => 'Old title',
      'status' => 1,
      'uid' => $source->id(),
    ]);
    $node->setRevisionUserId($source->id());
    $node->save();
    $old_revision_id = $node->getRevisionId();

    $node->setNewRevision(TRUE);
    $node->setTitle('Current title');
    $node->setOwnerId($source->id());
    $node->setRevisionUserId($current_editor->id());
    $node->save();
    $current_revision_id = $node->getRevisionId();
    $revision_count = $this->countRevisions();

    $entity_type_manager = $this->container->get('entity_type.manager');
    $this->cancelForEntityType('node', $source, CancellationHandlerInterface::METHOD_REASSIGN);

    $storage = $entity_type_manager->getStorage('node');
    $storage->resetCache();
    /** @var \Drupal\node\NodeInterface $current */
    $current = $storage->load($node->id());
    /** @var \Drupal\node\NodeInterface $old_revision */
    $old_revision = $storage->loadRevision($old_revision_id);

    self::assertSame($current_revision_id, $current->getRevisionId());
    self::assertSame('Current title', $current->label());
    self::assertSame('0', $current->getOwnerId());
    self::assertSame('0', $old_revision->getOwnerId());
    self::assertSame('0', $old_revision->getRevisionUserId());
    self::assertSame($current_editor->id(), $current->getRevisionUserId());
    self::assertSame($revision_count, $this->countRevisions());
  }

  /**
   * Tests that reassignment covers translations without changing their data.
   */
  public function testReassignUpdatesTranslations(): void {
    $source = User::create(['name' => 'source', 'status' => 1]);
    $source->save();

    $node = Node::create([
      'type' => 'test',
      'langcode' => 'en',
      'title' => 'English title',
      'status' => 1,
      'uid' => $source->id(),
    ]);
    $node->addTranslation('fr', [
      'title' => 'Titre français',
      'status' => 1,
      'uid' => $source->id(),
    ]);
    $node->setRevisionUserId($source->id());
    $node->save();
    $revision_id = $node->getRevisionId();

    $term = Term::create([
      'vid' => 'test',
      'name' => 'Term',
      'revision_user' => $source->id(),
    ]);
    $term->save();

    $menu_link = MenuLinkContent::create([
      'title' => 'Menu link',
      'link' => ['uri' => 'internal:/'],
      'menu_name' => 'tools',
      'revision_user' => $source->id(),
    ]);
    $menu_link->save();

    $this->cancelForEntityType('node', $source, CancellationHandlerInterface::METHOD_REASSIGN);
    $this->cancelForEntityType('menu_link_content', $source, CancellationHandlerInterface::METHOD_REASSIGN);
    $this->cancelForEntityType('taxonomy_term', $source, CancellationHandlerInterface::METHOD_REASSIGN);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache();
    /** @var \Drupal\node\NodeInterface $reloaded */
    $reloaded = $storage->load($node->id());

    self::assertSame($revision_id, $reloaded->getRevisionId());
    self::assertSame('English title', $reloaded->label());
    self::assertSame('0', $reloaded->getOwnerId());
    self::assertSame('Titre français', $reloaded->getTranslation('fr')->label());
    self::assertSame('0', $reloaded->getTranslation('fr')->getOwnerId());
    self::assertSame('0', $reloaded->getRevisionUserId());
    self::assertSame(1, $this->countRevisions());

    $term_storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $term_storage->resetCache([$term->id()]);
    self::assertSame('0', $term_storage->load($term->id())->getRevisionUserId());

    $menu_link_storage = $this->container->get('entity_type.manager')->getStorage('menu_link_content');
    $menu_link_storage->resetCache([$menu_link->id()]);
    self::assertSame('0', $menu_link_storage->load($menu_link->id())->getRevisionUserId());
  }

  /**
   * Tests deletion only anonymizes revision authors on another owner's node.
   */
  public function testDeletePreservesRevisionAuthoredBySource(): void {
    $source = User::create(['name' => 'source', 'status' => 1]);
    $source->save();
    $owner = User::create(['name' => 'owner', 'status' => 1]);
    $owner->save();

    $node = Node::create([
      'type' => 'test',
      'title' => 'Old title',
      'status' => 1,
      'uid' => $owner->id(),
    ]);
    $node->setRevisionUserId($source->id());
    $node->save();
    $old_revision_id = $node->getRevisionId();

    $node->setNewRevision(TRUE);
    $node->setTitle('Current title');
    $node->setOwnerId($owner->id());
    $node->setRevisionUserId($owner->id());
    $node->save();
    $current_revision_id = $node->getRevisionId();
    $revision_count = $this->countRevisions();

    $this->cancelForEntityType('node', $source, CancellationHandlerInterface::METHOD_DELETE);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache();
    /** @var \Drupal\node\NodeInterface $current */
    $current = $storage->load($node->id());
    /** @var \Drupal\node\NodeInterface $old_revision */
    $old_revision = $storage->loadRevision($old_revision_id);

    self::assertSame($current_revision_id, $current->getRevisionId());
    self::assertSame($owner->id(), $current->getOwnerId());
    self::assertSame($owner->id(), $old_revision->getOwnerId());
    self::assertSame('0', $old_revision->getRevisionUserId());
    self::assertSame($owner->id(), $current->getRevisionUserId());
    self::assertSame($revision_count, $this->countRevisions());
  }

  /**
   * Tests that unpublishing keeps current and revision tables consistent.
   */
  public function testUnpublishPreservesRevisionHistory(): void {
    $source = User::create(['name' => 'source', 'status' => 1]);
    $source->save();

    $node = Node::create([
      'type' => 'test',
      'title' => 'Old title',
      'status' => 1,
      'uid' => $source->id(),
    ]);
    $node->setRevisionUserId($source->id());
    $node->save();
    $old_revision_id = $node->getRevisionId();

    $node->setNewRevision(TRUE);
    $node->setTitle('Current title');
    $node->save();
    $current_revision_id = $node->getRevisionId();
    $revision_count = $this->countRevisions();

    $this->cancelForEntityType('node', $source, CancellationHandlerInterface::METHOD_BLOCK_UNPUBLISH);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache();
    /** @var \Drupal\node\NodeInterface $current */
    $current = $storage->load($node->id());
    /** @var \Drupal\node\NodeInterface $old_revision */
    $old_revision = $storage->loadRevision($old_revision_id);

    self::assertSame($current_revision_id, $current->getRevisionId());
    self::assertSame('Current title', $current->label());
    self::assertFalse($current->isPublished());
    self::assertTrue($old_revision->isPublished());
    self::assertSame($revision_count, $this->countRevisions());

    $database = $this->container->get('database');
    $current_status = $database->select('node_field_data', 'nfd')
      ->fields('nfd', ['status'])
      ->condition('nid', $node->id())
      ->execute()
      ->fetchField();
    $current_revision_status = $database->select('node_field_revision', 'nfr')
      ->fields('nfr', ['status'])
      ->condition('vid', $current_revision_id)
      ->execute()
      ->fetchField();
    self::assertSame('0', (string) $current_status);
    self::assertSame('0', (string) $current_revision_status);
  }

  /**
   * Tests that only translations owned by the account are unpublished.
   */
  public function testUnpublishOnlyUpdatesOwnedTranslations(): void {
    $source = User::create(['name' => 'source', 'status' => 1]);
    $source->save();
    $other_owner = User::create(['name' => 'other-owner', 'status' => 1]);
    $other_owner->save();

    $node = Node::create([
      'type' => 'test',
      'langcode' => 'en',
      'title' => 'English title',
      'status' => 1,
      'uid' => $source->id(),
    ]);
    $node->addTranslation('fr', [
      'title' => 'Titre français',
      'status' => 1,
      'uid' => $other_owner->id(),
    ]);
    $node->save();
    $revision_id = $node->getRevisionId();

    $this->cancelForEntityType(
      'node',
      $source,
      CancellationHandlerInterface::METHOD_BLOCK_UNPUBLISH,
    );

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$node->id()]);
    /** @var \Drupal\node\NodeInterface $reloaded */
    $reloaded = $storage->load($node->id());
    $french = $reloaded->getTranslation('fr');

    self::assertSame($revision_id, $reloaded->getRevisionId());
    self::assertFalse($reloaded->isPublished());
    self::assertSame($source->id(), $reloaded->getOwnerId());
    self::assertTrue($french->isPublished());
    self::assertSame($other_owner->id(), $french->getOwnerId());
    self::assertSame(1, $this->countRevisions());
  }

  /**
   * Tests that METHOD_BLOCK leaves entities untouched.
   */
  public function testBlockMethodIsNoOp(): void {
    $source = User::create(['name' => 'source', 'status' => 1]);
    $source->save();

    $node = Node::create([
      'type' => 'test',
      'title' => 'Title',
      'status' => 1,
      'uid' => $source->id(),
    ]);
    $node->setRevisionUserId($source->id());
    $node->save();
    $revision_id = $node->getRevisionId();

    $this->cancelForEntityType('node', $source, CancellationHandlerInterface::METHOD_BLOCK);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache();
    /** @var \Drupal\node\NodeInterface $reloaded */
    $reloaded = $storage->load($node->id());

    self::assertSame($revision_id, $reloaded->getRevisionId());
    self::assertTrue($reloaded->isPublished());
    self::assertSame($source->id(), $reloaded->getOwnerId());
    self::assertSame($source->id(), $reloaded->getRevisionUserId());
    self::assertSame(1, $this->countRevisions());
  }

  /**
   * Invokes the cancellation handler for an entity type.
   */
  private function cancelForEntityType(string $entity_type_id, UserInterface $account, string $method): void {
    $handler = $this->container->get('entity_type.manager')->getHandler($entity_type_id, 'user_cancel');
    self::assertInstanceOf(CancellationHandlerInterface::class, $handler);
    $handler->cancelAccount($account, $method);
  }

  /**
   * Counts all node revisions.
   */
  private function countRevisions(): int {
    return count($this->container->get('entity_type.manager')
      ->getStorage('node')
      ->getQuery()
      ->allRevisions()
      ->accessCheck(FALSE)
      ->execute());
  }

}
