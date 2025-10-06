<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Kernel;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\CancellationHandlerInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how comments react to user cancellation.
 */
#[Group('comment')]
class UserCancellationTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use UserCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'field',
    'node',
    'filter',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('comment', 'comment_entity_statistics');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig('node');
    $this->installConfig('user');
    $this->installConfig('filter');

    CommentType::create([
      'id' => 'test',
      'label' => 'Test',
      'target_entity_type_id' => 'node',
    ])->save();

    $this->createContentType(['type' => 'page']);

    FieldStorageConfig::create([
      'type' => 'comment',
      'entity_type' => 'node',
      'field_name' => 'comments',
    ])->save();

    FieldConfig::create([
      'field_name' => 'comments',
      'entity_type' => 'node',
      'bundle' => 'page',
    ])->save();

    $this->container->get('config.factory')
      ->getEditable('user.settings')
      ->set('anonymous', 'Mysterious Stranger')
      ->save();
  }

  /**
   * Tests how comment entities handle user cancellation.
   */
  public function testUserCancellation(): void {
    // With the BLOCK_UNPUBLISH method, the comment should still be associated
    // with the cancelled account, but be unpublished.
    $user = $this->createUser();
    $node = $this->createNode();
    $comment = $this->createComment($user, $node);
    user_cancel([], $user->id(), CancellationHandlerInterface::METHOD_BLOCK_UNPUBLISH);
    $comment = Comment::load($comment->id());
    $this->assertSame($user->id(), $comment->getOwnerId());
    $this->assertFalse($comment->isPublished());

    // With the REASSIGN method, the comment should still be published, but
    // associated with the anonymous user.
    $user = $this->createUser();
    $comment = $this->createComment($user, $node);
    user_cancel([], $user->id(), CancellationHandlerInterface::METHOD_REASSIGN);
    $comment = Comment::load($comment->id());
    $this->assertTrue($comment->getOwner()->isAnonymous());
    $this->assertSame('Mysterious Stranger', $comment->getAuthorName());
    $this->assertTrue($comment->isPublished());

    // The DELETE method should delete the comment outright.
    $user = $this->createUser();
    $comment = $this->createComment($user, $node);
    user_cancel([], $user->id(), CancellationHandlerInterface::METHOD_DELETE);
    $this->assertNull(Comment::load($comment->id()));
  }

  /**
   * Creates a published comment associated with a user account.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account which should own the comment.
   * @param \Drupal\node\NodeInterface $node
   *   The node this comment is for.
   *
   * @return \Drupal\comment\Entity\Comment
   *   The saved comment entity.
   */
  protected function createComment(UserInterface $user, NodeInterface $node): Comment {
    $comment = Comment::create([
      'comment_type' => 'test',
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'field_name' => 'comments',
    ]);
    $comment->setOwner($user)->setPublished()->save();
    $this->assertSame($user->id(), $comment->getOwnerId());
    $this->assertTrue($comment->isPublished());

    return $comment;
  }

}
