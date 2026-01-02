<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Kernel;

use Drupal\comment\CommentFieldItemList;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\comment\Traits\CommentCreationTrait;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test comment creation access control.
 */
#[CoversMethod(Comment::class, 'access')]
#[CoversMethod(CommentFieldItemList::class, 'access')]
#[Group('comment')]
#[RunTestsInSeparateProcesses]
class CommentCreationAccessTest extends KernelTestBase {

  use CommentCreationTrait;
  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'comment_test',
    'entity_test',
    'field',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installEntitySchema('comment');
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installConfig(['comment']);

    $this->createCommentType('entity_test', ['id' => 'comment']);
    $this->addDefaultCommentField('entity_test', 'entity_test');
  }

  /**
   * Tests that context is passed to the comment creation access control.
   */
  public function testContextForCommentCreationAccessCheck(): void {
    $state = $this->container->get('state');

    // Create a testing host entity.
    $entity = EntityTest::create();
    $entity->save();

    $comment = $this->createComment([
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
    ]);

    /** @var \Drupal\comment\CommentFieldItemList $field */
    $field = $entity->get('comment');

    // Check that the comment create access handler receives the commented
    // entity as context when called from the comment entity ::access() method.
    $comment->access('create');
    $create_access_context = $state->get('comment_test.create_access.context');
    $this->assertSame($entity->id(), $create_access_context['commented_entity']->id());

    $comment->access('reply');
    $create_access_context = $state->get('comment_test.create_access.context');
    $this->assertSame($entity->id(), $create_access_context['commented_entity']->id());

    // Check the same for comment field item list ::access() method.
    $field->access('create');
    $create_access_context = $state->get('comment_test.create_access.context');
    $this->assertSame($entity->id(), $create_access_context['commented_entity']->id());

    // Reply to comment.
    $reply = $this->createComment(['pid' => $comment->id()]);

    // Check that the comment create access handler receives the commented
    // entity and the parent comment entity as context.
    $reply->access('create');
    $create_access_context = $state->get('comment_test.create_access.context');
    $this->assertSame($entity->id(), $create_access_context['commented_entity']->id());
  }

}
