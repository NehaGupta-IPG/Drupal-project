<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Tests node storage.
 *
 * @group node
 * @coversDefaultClass \Drupal\node\NodeStorage
 */
class NodeStorageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'system',
    'field',
    'language',
    'content_translation',
  ];

  /**
   * An unsaved node for testing.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $testNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);

    $nodeType = NodeType::create([
      'type' => 'test',
      'name' => 'test',
      'revision' => TRUE,
    ]);
    $nodeType->save();
    $this->testNode = Node::create([
      'title' => 'test node en',
      'type' => $nodeType->id(),
      'language' => 'en',
    ]);

    ConfigurableLanguage::createFromLangcode('es')->save();
  }

  /**
   * Test getting revisions where a user is set as author.
   *
   * @covers ::userRevisionIds
   */
  public function testUserRevisionIds(): void {
    $owner = User::create(['name' => 'test user']);
    $owner->save();

    $node = $this->testNode;
    $node->setOwner($owner)->save();

    /** @var \Drupal\node\NodeStorageInterface $nodeStorage */
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    $revisionIds = $nodeStorage->userRevisionIds($owner);
    $this->assertEquals([$node->getRevisionId()], $revisionIds);
  }

  /**
   * Test getting revisions where a user is set as author but grouped by lang.
   *
   * @covers ::userRevisionIdsGroupedByLanguage
   */
  public function testUserRevisionIdsGroupedByLanguage(): void {
    $user1 = User::create(['name' => 'test user 1']);
    $user1->save();

    $node = $this->testNode;
    $node
      ->setSyncing(TRUE)
      ->setOwner($user1)
      ->setRevisionUser($user1)
      ->save();
    $firstRevisionId = $node->getRevisionId();

    // Create another revision owned by another user.
    $user2 = User::create(['name' => 'test user 2']);
    $user2->save();
    $node->setSyncing(FALSE);
    $node->setNewRevision();
    $node->setRevisionUser($user2);
    $node->addTranslation('es', ['title' => 'test node es', 'uid' => $user2->id()]);
    $node->save();
    $secondRevisionId = $node->getRevisionId();

    $this->assertNotEquals($firstRevisionId, $secondRevisionId);

    /** @var \Drupal\node\NodeStorageInterface $nodeStorage */
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');

    $revisionIds = $nodeStorage->userRevisionIdsGroupedByLanguage($user1);
    $this->assertEquals(['en' => [$firstRevisionId, $secondRevisionId]], $revisionIds);
    $revisionIds = $nodeStorage->userRevisionIdsGroupedByLanguage($user2);
    $this->assertEquals(['es' => [$secondRevisionId]], $revisionIds);
  }

  /**
   * Test getting revisions created by a user.
   *
   * @covers ::userRevisionIdsByRevisionAuthor
   */
  public function testUserRevisionIdsByRevisionAuthor(): void {
    $user1 = User::create(['name' => 'test user 1']);
    $user1->save();

    $node = $this->testNode;
    $node
      ->setSyncing(TRUE)
      ->setOwner($user1)
      ->setRevisionUser($user1)
      ->save();
    $firstRevisionId = $node->getRevisionId();

    // Create another revision owned by another user.
    $user2 = User::create(['name' => 'test user 2']);
    $user2->save();
    $node->setSyncing(FALSE);
    $node->setNewRevision();
    $node->setRevisionUser($user2);
    $node->save();
    $secondRevisionId = $node->getRevisionId();

    $this->assertNotEquals($firstRevisionId, $secondRevisionId);

    /** @var \Drupal\node\NodeStorageInterface $nodeStorage */
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');

    $revisionIds = $nodeStorage->userRevisionIdsByRevisionAuthor($user1);
    $this->assertEquals([$firstRevisionId], $revisionIds);
    $revisionIds = $nodeStorage->userRevisionIdsByRevisionAuthor($user2);
    $this->assertEquals([$secondRevisionId], $revisionIds);
  }

}
