<?php

namespace Drupal\Core\Cache\EventSubscriber;

use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Database\Event\TransactionCommitEvent;
use Drupal\Core\Database\Event\TransactionEventBase;
use Drupal\Core\Database\Event\TransactionRollbackEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for cache tags invalidation.
 */
class CacheTagsDeferredInvalidationSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected readonly CacheTagsChecksumInterface $databaseCacheTagsChecksumInvalidator,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      TransactionCommitEvent::class => 'onTransactionCommit',
      TransactionRollbackEvent::class => 'onUnsuccessfulTransaction',
    ];
  }

  /**
   * Post-commit invalidation of deferred cache tags.
   *
   * @param \Drupal\Core\Database\Event\TransactionCommitEvent $event
   *   The commit event.
   */
  public function onTransactionCommit(TransactionCommitEvent $event): void {
    $this->databaseCacheTagsChecksumInvalidator->invalidateDelayedTags();
  }

  /**
   * Reset deferred cache tags in case of unsuccessful transaction.
   *
   * @param \Drupal\Core\Database\Event\TransactionEventBase $event
   *   The transaction event.
   */
  public function onUnsuccessfulTransaction(TransactionEventBase $event): void {
    $this->databaseCacheTagsChecksumInvalidator->resetDelayedTags();
  }

}
