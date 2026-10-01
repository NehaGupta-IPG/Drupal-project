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
use PHPUnit\Framework\Attributes\DataProvider;
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
    $field = $entity->get('comment');

    $field->access('create');
    $this->assertEquals([
      'commented_entity' => 'entity_test:',
      'field' => 'comment',
    ], $state->get('comment_test.create_access.context'));
    $this->assertFalse($state->get('comment_test.create_access.cid'));
    $entity->save();

    $unsaved_comment = $this->createComment([
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
    ], FALSE);

    $field = $entity->get('comment');
    $this->assertInstanceOf(CommentFieldItemList::class, $field);

    // Check that the comment create access handler receives the commented
    // entity as context when called from the comment entity ::access() method.
    $unsaved_comment->access('create');
    $this->assertEquals([
      'commented_entity' => 'entity_test:1',
      'field' => 'comment',
    ], $state->get('comment_test.create_access.context'));
    $this->assertSame('create:comment:entity_test:1:comment:', $state->get('comment_test.create_access.cid'));

    $unsaved_comment->access('reply');
    $this->assertEquals([
      'commented_entity' => 'entity_test:1',
      'field' => 'comment',
      'parent' => 'comment:',
    ], $state->get('comment_test.create_access.context'));
    // Access result of a new comment is not cached.
    $this->assertFalse($state->get('comment_test.create_access.cid'));

    // Create access result should be cached in case of a saved comment.
    $comment = $this->createComment([
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
    ]);
    $comment->access('reply');
    $this->assertEquals([
      'commented_entity' => 'entity_test:1',
      'field' => 'comment',
      'parent' => 'comment:1',
    ], $state->get('comment_test.create_access.context'));
    $this->assertSame('create:comment:entity_test:1:comment:1', $state->get('comment_test.create_access.cid'));

    // Check the same for comment field item list ::access() method.
    $field->access('create');
    $this->assertEquals([
      'commented_entity' => 'entity_test:1',
      'field' => 'comment',
    ], $state->get('comment_test.create_access.context'));
    $this->assertSame('create:comment:entity_test:1:comment:', $state->get('comment_test.create_access.cid'));
    $state->delete('comment_test.create_access.cid');

    // Reply to comment.
    $reply = $this->createComment(['pid' => $comment->id()]);

    // Check that the comment create access handler receives the commented
    // entity and the parent comment entity as context.
    $reply->access('create');
    $this->assertEquals([
      'commented_entity' => 'entity_test:1',
      'field' => 'comment',
      'parent' => 'comment:1',
    ], $state->get('comment_test.create_access.context'));
    $this->assertSame('create:comment:entity_test:1:comment:1', $state->get('comment_test.create_access.cid'));
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
   * Tests that forbiddenMessage() shows login link when authenticated can post.
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
   * When no commented_entity or field is in context, buildCreateAccessCid()
   * must return NULL, so createAccess() bypasses caching. Otherwise, the
   * ambiguous CID would be shared across all context-less calls and could serve
   * stale results for the wrong entity.
   */
  #[DataProvider('createAccessCaching')]
  public function testCreateAccessCaching(array $context, bool $shouldBeCached): void {
    $entityTypeManager = $this->container->get('entity_type.manager');
    if (array_key_exists('commented_entity', $context)) {
      $context['commented_entity'] = $entityTypeManager->getStorage('entity_test')->create();
      $context['commented_entity']->save();
    }

    $state = $this->container->get('state');
    $access_handler = $entityTypeManager->getAccessControlHandler('comment');
    $account = User::getAnonymousUser();

    $access_handler->createAccess('comment', $account, $context, TRUE);
    if ($shouldBeCached) {
      $this->assertIsString($state->get('comment_test.create_access.cid'));
    }
    else {
      $this->assertFalse($state->get('comment_test.create_access.cid'));
      $this->assertNotNull($state->get('comment_test.check_create_access.account'));
    }

    $state->delete('comment_test.create_access.cid');
    $state->delete('comment_test.check_create_access.context');

    // Second call, same account. Without context, this shouldn't be a cache
    // hit, so account or
    $access_handler->createAccess('comment', $account, $context, TRUE);
    $cid = $state->get('comment_test.create_access.cid');

    if ($shouldBeCached) {
      $this->assertIsString($cid);
      // ::checkCreateAccess shouldn't be invoked, so context state value must
      // be empty (unset).
      $this->assertNull(
        $state->get('comment_test.check_create_access.context'),
        "checkCreateAccess() was invoked but it wasn't expected to be called again",
      );
    }
    else {
      $this->assertFalse(
        $state->get('comment_test.create_access.cid'),
        'checkCreateAccess() must be called again when no commented entity context is provided.',
      );
    }
  }

  /**
   * Provides testing cases for ::testCreateAccessCaching()
   *
   * @return array[]
   *   The test cases.
   *
   * @see self::testCreateAccessCaching()
   */
  public static function createAccessCaching(): array {
    return [
      'Empty context' => [[], FALSE],
      'Only host entity' => [['commented_entity' => 1], FALSE],
      'Only field' => [['field' => 'foo'], FALSE],
      'Cached result' => [['commented_entity' => 1, 'field' => 'foo'], TRUE],
    ];
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
    $this->assertSame('entity_test:1', $context['commented_entity']);

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
    $this->assertSame('entity_test_rev:1', $context['commented_entity']);
  }

}
