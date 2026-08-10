<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\CancellationHandlerInterface;
use Drupal\user\EntityCancellationProcessor;
use Drupal\user\Entity\User;
use Drupal\user_cancel_order_test\Hook\UserCancelOrderTestHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Batch API path of the entity cancellation processor.
 *
 * The synchronous path is covered by RevisionSafeCancellationHandlerTest.
 * This test exercises scheduling and successive batch passes.
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
final class RevisionSafeCancellationBatchTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'field',
    'filter',
    'language',
    'node',
    'system',
    'text',
    'user',
    'user_cancel_order_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['filter', 'node', 'user']);

    NodeType::create([
      'type' => 'test',
      'name' => 'Test',
    ])->save();
    CommentType::create([
      'id' => 'test',
      'label' => 'Test',
      'target_entity_type_id' => 'node',
    ])->save();
    FieldStorageConfig::create([
      'type' => 'comment',
      'entity_type' => 'node',
      'field_name' => 'comments',
    ])->save();
    FieldConfig::create([
      'field_name' => 'comments',
      'entity_type' => 'node',
      'bundle' => 'test',
    ])->save();
    ConfigurableLanguage::createFromLangcode('fr')->save();
  }

  /**
   * Handlers and legacy hooks join the batch in contract order.
   */
  public function testUserCancelBatchOrder(): void {
    $source = User::create(['name' => 'source', 'status' => 1]);
    $source->save();
    $node = Node::create([
      'type' => 'test',
      'title' => 'Node',
      'status' => 1,
      'uid' => $source->id(),
    ]);
    $node->save();
    $comment = Comment::create([
      'comment_type' => 'test',
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'field_name' => 'comments',
      'uid' => $source->id(),
    ]);
    $comment->save();

    $state = $this->container->get('state');
    $state->set('user_cancel_order_test.node_id', $node->id());

    user_cancel([], (int) $source->id(), CancellationHandlerInterface::METHOD_REASSIGN);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$node->id()]);
    self::assertSame($source->id(), $storage->load($node->id())->getOwnerId());
    self::assertSame(
      (int) $source->id(),
      $state->get('user_cancel_order_test.synchronous_owner_id'),
    );

    $batch = &batch_get();
    self::assertCount(5, $batch['sets']);
    self::assertEmpty($batch['sets'][0]['operations']);
    self::assertSame([EntityCancellationProcessor::class, 'batchProcess'], $batch['sets'][1]['operations'][0][0]);
    self::assertSame('comment', $batch['sets'][1]['operations'][0][1][0]);
    self::assertSame('node', $batch['sets'][2]['operations'][0][1][0]);
    self::assertSame(
      [UserCancelOrderTestHooks::class, 'recordOwner'],
      $batch['sets'][3]['operations'][0][0],
    );
    self::assertSame('_user_cancel', $batch['sets'][4]['operations'][0][0]);

    // Run the actual Batch API pipeline, including the final account deletion.
    $batch['progressive'] = FALSE;
    batch_process();

    $this->container->get('entity_type.manager')->getStorage('user')
      ->resetCache([$source->id()]);
    self::assertNull(User::load($source->id()));
    self::assertSame(
      0,
      $state->get('user_cancel_order_test.batch_owner_id'),
    );
    $storage->resetCache([$node->id()]);
    self::assertSame('0', $storage->load($node->id())->getOwnerId());
    self::assertSame('0', Comment::load($comment->id())->getOwnerId());
  }

  /**
   * An invalid handler aborts cancellation before the account is changed.
   */
  public function testInvalidHandlerStopsCancellation(): void {
    $source = User::create(['name' => 'source', 'status' => 1]);
    $source->save();

    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type_manager->getDefinition('node')
      ->setHandlerClass('user_cancel', \stdClass::class);

    try {
      user_cancel([], (int) $source->id(), CancellationHandlerInterface::METHOD_BLOCK);
      self::fail('An invalid cancellation handler must stop account cancellation.');
    }
    catch (InvalidPluginDefinitionException $exception) {
      self::assertStringContainsString('"user_cancel" handler', $exception->getMessage());
      self::assertNotNull(User::load($source->id()));
    }
    finally {
      $batch = &batch_get();
      $batch = [];
    }
  }

  /**
   * Tests that only matching translations receive the anonymous author name.
   */
  public function testReassignOnlyChangesMatchingCommentTranslations(): void {
    $source = User::create(['name' => 'source', 'status' => 1]);
    $source->save();
    $node = Node::create([
      'type' => 'test',
      'title' => 'Node',
      'status' => 1,
      'uid' => $source->id(),
    ]);
    $node->save();

    $anonymous_name = (string) $this->container->get('config.factory')
      ->get('user.settings')
      ->get('anonymous');

    $french_author_name = 'Visiteur français';
    $comment = Comment::create([
      'comment_type' => 'test',
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'field_name' => 'comments',
      'langcode' => 'en',
      'uid' => $source->id(),
    ]);
    $comment->addTranslation('fr', [
      'uid' => 0,
      'name' => $french_author_name,
    ]);
    $comment->save();

    $handler = $this->container->get('entity_type.manager')
      ->getHandler('comment', 'user_cancel');
    self::assertInstanceOf(CancellationHandlerInterface::class, $handler);
    $handler->cancelAccount(
      $source,
      CancellationHandlerInterface::METHOD_REASSIGN,
    );

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('comment');
    $storage->resetCache([$comment->id()]);
    /** @var \Drupal\comment\CommentInterface $reloaded */
    $reloaded = $storage->load($comment->id());
    $french = $reloaded->getTranslation('fr');

    self::assertSame('0', $reloaded->getOwnerId());
    self::assertSame($anonymous_name, $reloaded->getAuthorName());
    self::assertSame('0', $french->getOwnerId());
    self::assertSame($french_author_name, $french->getAuthorName());
  }

  /**
   * The batch callback must drain every matching entity in successive passes.
   */
  public function testBatchProcessDrainsAllOwnedEntities(): void {
    $source = User::create(['name' => 'source', 'status' => 1]);
    $source->save();

    for ($i = 0; $i < 55; $i++) {
      $node = Node::create([
        'type' => 'test',
        'title' => 'Node ' . $i,
        'status' => 1,
        'uid' => $source->id(),
      ]);
      $node->save();
    }

    $plan = [
      'operation' => 'reassign',
      'field' => 'uid',
      'uid' => (int) $source->id(),
      'all_revisions' => TRUE,
      'reassignment_values' => [],
    ];
    $context = [];
    $passes = 1;
    EntityCancellationProcessor::batchProcess('node', $plan, $context);

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    while (($context['finished'] ?? 1) < 1 && $passes < 20) {
      EntityCancellationProcessor::batchProcess('node', $plan, $context);
      $passes++;
    }

    self::assertSame(1, $context['finished']);
    self::assertGreaterThan(1, $passes, 'The batch callback ran in more than one pass.');

    $storage->resetCache();
    $remaining = (int) $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $source->id())
      ->count()
      ->execute();
    self::assertSame(0, $remaining);
  }

  /**
   * A revision deleted after the query is skipped and advances the cursor.
   */
  public function testBatchProcessSkipsRevisionMissingAfterQuery(): void {
    $chunk_query = $this->createMock(QueryInterface::class);
    $chunk_query->method('accessCheck')->willReturnSelf();
    $chunk_query->method('condition')->willReturnSelf();
    $chunk_query->method('allRevisions')->willReturnSelf();
    $chunk_query->method('sort')->willReturnSelf();
    $chunk_query->method('range')->willReturnSelf();
    $chunk_query->expects($this->once())
      ->method('execute')
      // The revision disappears before the processor loads this query result.
      ->willReturn([42 => 7]);

    $storage = $this->createMock(ContentEntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('getQuery')
      ->willReturn($chunk_query);
    $storage->expects($this->once())
      ->method('loadRevision')
      ->with(42)
      ->willReturn(NULL);

    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->method('id')->willReturn('test');
    $entity_type->method('isRevisionable')->willReturn(TRUE);
    $entity_type->method('getKey')->with('revision')->willReturn('revision_id');

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinition')
      ->with('test')
      ->willReturn($entity_type);
    $entity_type_manager->method('getStorage')
      ->with('test')
      ->willReturn($storage);
    $this->container->set('entity_type.manager', $entity_type_manager);

    $context = [];
    EntityCancellationProcessor::batchProcess('test', [
      'operation' => 'reassign',
      'field' => 'uid',
      'uid' => 23,
      'all_revisions' => TRUE,
      'reassignment_values' => [],
    ], $context);

    self::assertSame(42, $context['sandbox']['cursor']);
    self::assertSame(1, $context['finished']);
  }

}
