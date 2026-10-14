<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\AccountCancellation;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests node behavior when users are cancelled.
 */
#[Group('node')]
#[RunTestsInSeparateProcesses]
class NodeUserCancelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'user', 'system', 'field', 'language'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['language']);
    // Create the anonymous user, so that Node::preSave() keeps it as the
    // revision author of the reassigned revisions instead of replacing it with
    // the node owner.
    User::create(['uid' => 0, 'name' => ''])->save();
    ConfigurableLanguage::createFromLangcode('es')->save();

    NodeType::create([
      'type' => 'test',
      'name' => 'test',
      'revision' => TRUE,
    ])->save();
  }

  /**
   * Confirm that the author has been assigned to the anonymous user.
   */
  public function testCancelReassignToAnonymous(): void {
    /** @var \Drupal\node\NodeStorageInterface $nodeStorage */
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');

    $user1 = User::create(['name' => 'test user 1']);
    $user1->save();
    $user2 = User::create(['name' => 'test user 2']);
    $user2->save();

    // Use a changed time in the past, so that an update of the changed time
    // can be detected.
    $node = Node::create([
      'title' => 'test node',
      'type' => 'test',
      'changed' => 1000,
    ]);

    // Set to published to test 'user_cancel_reassign' does not unpublish.
    $node
      ->setPublished()
      ->setOwner($user1)
      ->setRevisionUser($user1)
      ->save();
    $firstRevisionId = $node->getRevisionId();
    $node->setNewRevision();
    $node->setRevisionUser($user2)->save();
    $secondRevisionId = $node->getRevisionId();

    $this->assertNotEquals($firstRevisionId, $secondRevisionId);
    $changedTimes = [
      $firstRevisionId => $nodeStorage->loadRevision($firstRevisionId)->getChangedTime(),
      $secondRevisionId => $nodeStorage->loadRevision($secondRevisionId)->getChangedTime(),
    ];
    $this->assertEquals(1000, $changedTimes[$firstRevisionId]);

    \Drupal::service(AccountCancellation::class)->cancel([], (int) $user1->id(), 'user_cancel_reassign');

    // Confirm that no revisions were created and the changed times were not
    // updated.
    $this->assertEquals([$firstRevisionId, $secondRevisionId], array_keys($nodeStorage->getQuery()->accessCheck(FALSE)->allRevisions()->sort('vid')->execute()));
    foreach ($changedTimes as $revisionId => $changedTime) {
      $this->assertEquals($changedTime, $nodeStorage->loadRevision($revisionId)->getChangedTime());
    }

    // Test default revision.
    // Reload node.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $nodeStorage->load($node->id());
    $this->assertEquals($secondRevisionId, $node->getRevisionId());
    // Confirm that the author has been assigned to the anonymous user.
    $this->assertEquals(0, $node->getOwnerId());
    $this->assertEquals($user2->id(), $node->getRevisionUserId());
    // Ensure content is not unpublished, which 'user_cancel_block_unpublish'
    // method handles.
    $this->assertTrue($node->isPublished());

    /** @var \Drupal\node\NodeInterface $firstRevision */
    $firstRevision = $nodeStorage->loadRevision($firstRevisionId);
    $this->assertEquals(0, $firstRevision->getOwnerId());
    $this->assertEquals(0, $firstRevision->getRevisionUserId());
    $this->assertTrue($firstRevision->isPublished());

    /** @var \Drupal\node\NodeInterface $secondRevision */
    $secondRevision = $nodeStorage->loadRevision($secondRevisionId);
    $this->assertEquals(0, $secondRevision->getOwnerId());
    $this->assertEquals($user2->id(), $secondRevision->getRevisionUserId());
    $this->assertTrue($secondRevision->isPublished());
  }

  /**
   * Tests reassigning a translation owned by the cancelled user.
   *
   * The ownership of the other languages of the node must not change.
   */
  public function testCancelReassignTranslationOwner(): void {
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');

    $user1 = User::create(['name' => 'test user 1']);
    $user1->save();
    $user2 = User::create(['name' => 'test user 2']);
    $user2->save();

    // Create the node as the second user and translate it as the first user.
    $node = Node::create([
      'title' => 'test node',
      'type' => 'test',
      'langcode' => 'en',
      'uid' => $user2->id(),
      'revision_uid' => $user2->id(),
    ]);
    $node->save();
    $originalRevisionId = $node->getRevisionId();

    $node->setNewRevision();
    $node->addTranslation('es', [
      'title' => 'test node es',
      'uid' => $user1->id(),
    ])
      ->setRevisionUserId($user1->id())
      ->save();
    $translationRevisionId = $node->getRevisionId();

    \Drupal::service(AccountCancellation::class)->cancel([], (int) $user1->id(), 'user_cancel_reassign');

    // Confirm that no revisions were created.
    $this->assertEquals([$originalRevisionId, $translationRevisionId], array_keys($nodeStorage->getQuery()->accessCheck(FALSE)->allRevisions()->sort('vid')->execute()));

    /** @var \Drupal\node\NodeInterface $originalRevision */
    $originalRevision = $nodeStorage->loadRevision($originalRevisionId);
    $this->assertEquals($user2->id(), $originalRevision->getOwnerId());
    $this->assertEquals($user2->id(), $originalRevision->getRevisionUserId());

    /** @var \Drupal\node\NodeInterface $translationRevision */
    $translationRevision = $nodeStorage->loadRevision($translationRevisionId);
    $this->assertEquals($user2->id(), $translationRevision->getOwnerId());
    $this->assertEquals(0, $translationRevision->getTranslation('es')->getOwnerId());
    $this->assertEquals(0, $translationRevision->getRevisionUserId());
  }

}
