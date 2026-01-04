<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Unit\Entity;

use Drupal\comment\CommentStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\comment\Entity\Comment;
use Drupal\Core\Cache\CacheTagsInvalidator;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\comment\CommentStatisticsInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests comment acquires and releases the right lock.
 */
#[Group('comment')]
class CommentLockTest extends UnitTestCase {

  /**
   * Tests the lock behavior.
   */
  public function testLocks(): void {
    $container = new ContainerBuilder();
    $container->set('module_handler', $this->createMock(ModuleHandlerInterface::class));
    $container->set('current_user', $this->createMock(AccountInterface::class));
    $container->set('cache.test', $this->createMock(CacheBackendInterface::class));
    $container->set('comment.statistics', $this->createMock(CommentStatisticsInterface::class));
    $request_stack = new RequestStack();
    $request_stack->push(Request::create('/'));
    $container->set('request_stack', $request_stack);
    $container->setParameter('cache_bins', ['cache.test' => 'test']);
    $lock = $this->createMock(LockBackendInterface::class);
    $cid = 2;
    $lock_name = "comment:$cid:.00/";
    $lock->expects($this->once())
      ->method('acquire')
      ->with($lock_name, 30)
      ->willReturn(TRUE);
    $lock->expects($this->once())
      ->method('release')
      ->with($lock_name);
    $lock->expects($this->exactly(2))
      ->method($this->anything());
    $container->set('lock', $lock);

    $cache_tag_invalidator = $this->createMock(CacheTagsInvalidator::class);
    $container->set('cache_tags.invalidator', $cache_tag_invalidator);

    \Drupal::setContainer($container);
    $methods = get_class_methods(Comment::class);
    unset($methods[array_search('preSave', $methods)]);
    unset($methods[array_search('postSave', $methods)]);
    $methods[] = 'invalidateTagsOnSave';
    $comment = $this->getMockBuilder(Comment::class)
      ->disableOriginalConstructor()
      ->onlyMethods($methods)
      ->getMock();
    $comment->expects($this->once())
      ->method('isNew')
      ->willReturn(TRUE);
    $comment->expects($this->once())
      ->method('hasParentComment')
      ->willReturn(TRUE);
    $comment->expects($this->once())
      ->method('getParentComment')
      ->willReturn($comment);
    $comment->expects($this->once())
      ->method('getCommentedEntityId')
      ->willReturn($cid);
    $comment->expects($this->any())
      ->method('getThread')
      ->willReturn('');

    $anon_user = $this->createMock(AccountInterface::class);
    $anon_user->expects($this->any())
      ->method('isAnonymous')
      ->willReturn(TRUE);
    $comment->expects($this->any())
      ->method('getOwner')
      ->willReturn($anon_user);

    $parent_entity = $this->createMock(ContentEntityInterface::class);
    $parent_entity->expects($this->atLeastOnce())
      ->method('getCacheTagsToInvalidate')
      ->willReturn(['node:1']);
    $comment->expects($this->once())
      ->method('getCommentedEntity')
      ->willReturn($parent_entity);

    $entity_type = $this->createMock(EntityTypeInterface::class);
    $comment->expects($this->any())
      ->method('getEntityType')
      ->willReturn($entity_type);
    $storage = $this->createMock(CommentStorageInterface::class);

    // preSave() should acquire the lock. (This is what's really being tested.)
    $comment->preSave($storage);
    // Release the acquired lock before exiting the test.
    $comment->postSave($storage);
  }

}
