<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database\Stub\EventSubscriber;

use Drupal\Core\Database\Event\TransactionBeginEvent;
use Drupal\Core\Database\Event\TransactionBeginFailureEvent;
use Drupal\Core\Database\Event\TransactionCommitEvent;
use Drupal\Core\Database\Event\TransactionCommitFailureEvent;
use Drupal\Core\Database\Event\TransactionEventBase;
use Drupal\Core\Database\Event\TransactionReleaseSavepointEvent;
use Drupal\Core\Database\Event\TransactionReleaseSavepointFailureEvent;
use Drupal\Core\Database\Event\TransactionRollbackEvent;
use Drupal\Core\Database\Event\TransactionRollbackFailureEvent;
use Drupal\Core\Database\Event\TransactionRollbackToSavepointEvent;
use Drupal\Core\Database\Event\TransactionRollbackToSavepointFailureEvent;
use Drupal\Core\Database\Event\TransactionSavepointEvent;
use Drupal\Core\Database\Event\TransactionSavepointFailureEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Responds to database API transaction events.
 */
class StubTransactionEventSubscriber implements EventSubscriberInterface {

  /**
   * The last event dispatched.
   */
  public TransactionEventBase $lastEvent;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      TransactionBeginEvent::class => 'onTransactionEvent',
      TransactionBeginFailureEvent::class => 'onTransactionEvent',
      TransactionCommitEvent::class => 'onTransactionEvent',
      TransactionCommitFailureEvent::class => 'onTransactionEvent',
      TransactionReleaseSavepointEvent::class => 'onTransactionEvent',
      TransactionReleaseSavepointFailureEvent::class => 'onTransactionEvent',
      TransactionRollbackEvent::class => 'onTransactionEvent',
      TransactionRollbackFailureEvent::class => 'onTransactionEvent',
      TransactionRollbackToSavepointEvent::class => 'onTransactionEvent',
      TransactionRollbackToSavepointFailureEvent::class => 'onTransactionEvent',
      TransactionSavepointEvent::class => 'onTransactionEvent',
      TransactionSavepointFailureEvent::class => 'onTransactionEvent',
    ];
  }

  /**
   * Subscribes to a transaction event.
   *
   * @param \Drupal\Core\Database\Event\TransactionEventBase $event
   *   The transaction event.
   */
  public function onTransactionEvent(TransactionEventBase $event): void {
    $this->lastEvent = $event;
  }

}
