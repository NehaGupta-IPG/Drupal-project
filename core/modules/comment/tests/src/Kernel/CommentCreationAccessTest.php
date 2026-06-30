<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Kernel;

use Drupal\comment\CommentFieldItemList;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\comment\Traits\CommentCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
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

  /**
   * Tests that forbiddenMessage() shows nothing without post permission.
   */
  public function testForbiddenMessageWithoutPostPermission(): void {
    $this->installConfig(['user']);

    $entity = EntityTest::create();
    $entity->save();

    $this->assertEmpty(
      $this->container->get('comment.manager')->forbiddenMessage($entity, 'comment')
    );
  }

  /**
   * Tests that forbiddenMessage() shows a login link with post permission.
   *
   * CommentManager::forbiddenMessage() checks whether authenticated users can
   * post comments by loading the authenticated role and checking its
   * permissions directly, rather than proxying through a dummy user session.
   */
  public function testForbiddenMessageWithPostPermission(): void {
    $this->installConfig(['user']);

    Role::load(RoleInterface::AUTHENTICATED_ID)
      ->grantPermission('post comments')
      ->save();

    $entity = EntityTest::create();
    $entity->save();

    $message = $this->container->get('comment.manager')->forbiddenMessage($entity, 'comment');
    $this->assertNotEmpty($message);
    $this->assertStringContainsString('Log in', (string) $message);
  }

  /**
   * Tests that createAccess() without context skips the static cache.
   *
   * When no commented_entity is in context, buildCreateAccessCid() must return
   * NULL so createAccess() bypasses caching. Otherwise, the ambiguous CID
   * "create:comment:0" would be shared across all context-less calls and could
   * serve stale results for the wrong entity.
   */
  public function testCreateAccessWithoutContextSkipsCache(): void {
    $state = $this->container->get('state');

    $access_handler = $this->container->get('entity_type.manager')
      ->getAccessControlHandler('comment');

    $account = User::getAnonymousUser();

    $access_handler->createAccess('comment', $account, [], TRUE);
    $this->assertSame($state->get('comment_test.create_access.account'), $account->id());

    $state->delete('comment_test.create_access.account');

    // Second call, same account, still no context. Without the fix this would
    // be a cache hit on "create:comment:0" and checkCreateAccess() would NOT
    // fire again. With the fix (NULL CID) caching is skipped entirely.
    $access_handler->createAccess('comment', $account, [], TRUE);
    $this->assertSame(
      $state->get('comment_test.create_access.account'),
      $account->id(),
      'checkCreateAccess() must be called again when no commented entity context is provided.',
    );
  }

  /**
   * Tests that create access cache varies by commented entity type.
   *
   * Two host entities of different types but with the same ID (e.g. node:1 and
   * taxonomy_term:1) must not share a cached access result. The CID must
   * include the entity type to avoid stale cross-type cache hits.
   */
  public function testCreateAccessCacheVariesByEntityType(): void {
    $state = $this->container->get('state');

    $this->installEntitySchema('entity_test_rev');
    $this->createCommentType('entity_test_rev', ['id' => 'comment_rev']);
    $this->addDefaultCommentField('entity_test_rev', 'entity_test_rev', 'comment', comment_type_id: 'comment_rev');

    // Both entities get ID 1.
    $entity_test = EntityTest::create();
    $entity_test->save();
    $entity_test_rev = EntityTestRev::create();
    $entity_test_rev->save();

    $this->assertSame($entity_test->id(), $entity_test_rev->id(), 'Both entities share the same ID.');
    $this->assertNotSame($entity_test->getEntityTypeId(), $entity_test_rev->getEntityTypeId());

    $access_handler = $this->container->get('entity_type.manager')
      ->getAccessControlHandler('comment');
    $account = User::getAnonymousUser();

    $access_handler->createAccess('comment', $account, ['commented_entity' => $entity_test], TRUE);
    $context = $state->get('comment_test.create_access.context');
    $this->assertSame($entity_test->getEntityTypeId(), $context['commented_entity']->getEntityTypeId());

    $state->delete('comment_test.create_access.context');

    // Second call: createAccess for comment on entity_test_rev:1 (same ID,
    // different entity type). checkCreateAccess() must fire again — not serve
    // the cached result from entity_test:1.
    $access_handler->createAccess('comment', $account, ['commented_entity' => $entity_test_rev], TRUE);
    $context = $state->get('comment_test.create_access.context');
    $this->assertNotNull(
      $context,
      'checkCreateAccess() must be called for entity_test_rev:1, not cached from entity_test:1.',
    );
    $this->assertSame($entity_test_rev->getEntityTypeId(), $context['commented_entity']->getEntityTypeId());
  }

}
