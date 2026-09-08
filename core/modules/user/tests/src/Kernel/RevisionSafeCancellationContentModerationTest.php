<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel;

use Drupal\content_moderation\Entity\ContentModerationState as ContentModerationStateEntity;
use Drupal\content_moderation\Entity\ContentModerationStateInterface;
use Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\user\CancellationHandlerInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests revision-safe cancellation with Content Moderation.
 */
#[Group('user')]
#[Group('content_moderation')]
#[RunTestsInSeparateProcesses]
final class RevisionSafeCancellationContentModerationTest extends KernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'language',
    'node',
    'user',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['content_moderation']);

    ConfigurableLanguage::createFromLangcode('fr')->save();

    NodeType::create([
      'type' => 'test',
      'name' => 'Test',
    ])->save();
    $workflow = $this->createEditorialWorkflow();
    $workflow_type = $workflow->getTypePlugin();
    self::assertInstanceOf(ContentModerationInterface::class, $workflow_type);
    $workflow_type->addEntityTypeAndBundle('node', 'test');
    $workflow->save();
  }

  /**
   * Tests that a published default and newer draft keep their roles.
   */
  public function testReassignPreservesModeratedRevisionTopology(): void {
    $source = User::create(['name' => 'source', 'status' => 1]);
    $source->save();
    $draft_editor = User::create(['name' => 'draft-editor', 'status' => 1]);
    $draft_editor->save();

    $node = Node::create([
      'type' => 'test',
      'title' => 'Published title',
      'uid' => $source->id(),
      'moderation_state' => 'published',
    ]);
    $node->setRevisionUserId($source->id());
    $node->save();
    $published_revision_id = $node->getRevisionId();

    $node->setTitle('Draft title');
    $node->setOwnerId($source->id());
    $node->setRevisionUserId($draft_editor->id());
    $node->moderation_state->value = 'draft';
    $node->save();
    $draft_revision_id = $node->getRevisionId();
    self::assertFalse($node->isDefaultRevision());
    $revision_count = $this->countRevisions();

    $this->cancelForEntityType(
      'node',
      $source,
      CancellationHandlerInterface::METHOD_REASSIGN,
    );

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache();
    /** @var \Drupal\node\NodeInterface $published */
    $published = $storage->load($node->id());
    /** @var \Drupal\node\NodeInterface $draft */
    $draft = $storage->loadRevision($draft_revision_id);

    self::assertSame($published_revision_id, $published->getRevisionId());
    self::assertTrue($published->isDefaultRevision());
    self::assertTrue($published->isPublished());
    self::assertSame('published', $published->get('moderation_state')->value);
    self::assertSame('Published title', $published->label());
    self::assertSame('0', $published->getOwnerId());
    self::assertSame('0', $published->getRevisionUserId());

    self::assertSame($draft_revision_id, $draft->getRevisionId());
    self::assertFalse($draft->isDefaultRevision());
    self::assertFalse($draft->isPublished());
    self::assertSame('draft', $draft->get('moderation_state')->value);
    self::assertSame('Draft title', $draft->label());
    self::assertSame('0', $draft->getOwnerId());
    self::assertSame($draft_editor->id(), $draft->getRevisionUserId());
    self::assertSame($revision_count, $this->countRevisions());
  }

  /**
   * Tests block-unpublish under content moderation.
   *
   * The default revision must be unpublished without creating a new revision
   * while moving the current revision to the canonical draft state.
   */
  public function testUnpublishUsesCanonicalDraftState(): void {
    $source = User::create(['name' => 'source', 'status' => 1]);
    $source->save();

    $node = Node::create([
      'type' => 'test',
      'title' => 'Published title',
      'uid' => $source->id(),
      'moderation_state' => 'published',
    ]);
    $node->save();
    $published_revision_id = $node->getRevisionId();
    $revision_count = $this->countRevisions();

    $this->cancelForEntityType(
      'node',
      $source,
      CancellationHandlerInterface::METHOD_BLOCK_UNPUBLISH,
    );

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache();
    /** @var \Drupal\node\NodeInterface $reloaded */
    $reloaded = $storage->load($node->id());

    self::assertSame($published_revision_id, $reloaded->getRevisionId());
    self::assertTrue($reloaded->isDefaultRevision());
    self::assertFalse($reloaded->isPublished());
    self::assertSame('draft', $reloaded->get('moderation_state')->value);
    self::assertSame('Published title', $reloaded->label());
    self::assertSame($revision_count, $this->countRevisions());
  }

  /**
   * Tests unpublishing a non-default translation under content moderation.
   */
  public function testUnpublishUpdatesTranslationModerationState(): void {
    $source = User::create(['name' => 'source', 'status' => 1]);
    $source->save();
    $english_owner = User::create([
      'name' => 'english-owner',
      'status' => 1,
    ]);
    $english_owner->save();

    $node = Node::create([
      'type' => 'test',
      'langcode' => 'en',
      'title' => 'English title',
      'uid' => $english_owner->id(),
      'moderation_state' => 'published',
    ]);
    $node->save();
    $french = $node->addTranslation('fr', [
      'title' => 'Titre français',
      'uid' => $source->id(),
      'moderation_state' => 'published',
    ]);
    $french->save();
    $revision_id = $node->getRevisionId();
    $revision_count = $this->countRevisions();

    $this->cancelForEntityType(
      'node',
      $source,
      CancellationHandlerInterface::METHOD_BLOCK_UNPUBLISH,
    );

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('node');
    $storage->resetCache([$node->id()]);
    /** @var \Drupal\node\NodeInterface $reloaded */
    $reloaded = $storage->load($node->id());
    $french = $reloaded->getTranslation('fr');

    self::assertTrue($reloaded->isPublished());
    self::assertSame('published', $reloaded->moderation_state->value);
    self::assertFalse($french->isPublished());
    self::assertSame('draft', $french->moderation_state->value);
    self::assertSame($revision_id, $reloaded->getRevisionId());
    self::assertSame($revision_count, $this->countRevisions());
  }

  /**
   * Internal moderation state ownership is anonymized, never deleted.
   */
  public function testContentModerationStateOwnerIsReassigned(): void {
    $source = User::create(['name' => 'source', 'status' => 1]);
    $source->save();
    $node_owner = User::create(['name' => 'node-owner', 'status' => 1]);
    $node_owner->save();

    $node = Node::create([
      'type' => 'test',
      'title' => 'Published title',
      'uid' => $node_owner->id(),
      'moderation_state' => 'published',
    ]);
    $node->save();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('content_moderation_state');
    $moderation_state = ContentModerationStateEntity::loadFromModeratedEntity($node);
    self::assertInstanceOf(ContentModerationStateInterface::class, $moderation_state);
    $revision_id = $moderation_state->getRevisionId();

    $methods = [
      CancellationHandlerInterface::METHOD_REASSIGN,
      CancellationHandlerInterface::METHOD_DELETE,
    ];
    foreach ($methods as $method) {
      $moderation_state->setOwnerId($source->id());
      $storage->save($moderation_state);

      $this->cancelForEntityType('content_moderation_state', $source, $method);

      $storage->resetCache();
      $moderation_state = $storage->loadRevision($revision_id);
      self::assertInstanceOf(ContentModerationStateInterface::class, $moderation_state);
      self::assertSame('0', $moderation_state->getOwnerId());
      self::assertNotNull(Node::load($node->id()));
    }
  }

  /**
   * Invokes an entity type's cancellation handler.
   */
  private function cancelForEntityType(
    string $entity_type_id,
    UserInterface $account,
    string $method,
  ): void {
    $handler = $this->container->get('entity_type.manager')
      ->getHandler($entity_type_id, 'user_cancel');
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
