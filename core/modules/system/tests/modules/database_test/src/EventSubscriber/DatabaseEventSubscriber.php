<?php

declare(strict_types=1);

namespace Drupal\database_test\EventSubscriber;

use Drupal\Core\Database\Event\StatementExecutionEndEvent;
use Drupal\Core\Database\Event\StatementExecutionFailureEvent;
use Drupal\Core\Database\Event\StatementExecutionStartEvent;
use Drupal\Core\Database\Event\TransactionAutoCommitEvent;
use Drupal\Core\Database\Event\TransactionBeginEvent;
use Drupal\Core\Database\Event\TransactionCommitEvent;
use Drupal\Core\Database\Event\TransactionReleaseSavepointEvent;
use Drupal\Core\Database\Event\TransactionRollbackEvent;
use Drupal\Core\Database\Event\TransactionRollbackToSavepointEvent;
use Drupal\Core\Database\Event\TransactionSavepointEvent;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Responds to database API events.
 */
class DatabaseEventSubscriber implements EventSubscriberInterface {

  /**
   * A counter of started statement executions.
   */
  public int $countStatementStarts = 0;

  /**
   * A counter of finished statement executions.
   */
  public int $countStatementEnds = 0;

  /**
   * A counter of failed statement executions.
   */
  public int $countStatementFailures = 0;

  /**
   * A map of statements being executed.
   */
  public array $statementIdsInExecution = [];

  public function __construct(
    #[Autowire(service: 'keyvalue')]
    protected KeyValueFactoryInterface $keyValue,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      StatementExecutionStartEvent::class => 'onStatementExecutionStart',
      StatementExecutionEndEvent::class => 'onStatementExecutionEnd',
      StatementExecutionFailureEvent::class => 'onStatementExecutionFailure',
      TransactionBeginEvent::class => 'onTransactionBegin',
      TransactionSavepointEvent::class => 'onTransactionSavepoint',
      TransactionAutoCommitEvent::class => 'onTransactionAutoCommit',
      TransactionCommitEvent::class => 'onTransactionCommit',
      TransactionReleaseSavepointEvent::class => 'onTransactionReleaseSavepoint',
      TransactionRollbackEvent::class => 'onTransactionRollback',
      TransactionRollbackToSavepointEvent::class => 'onTransactionRollbackToSavepoint',
    ];
  }

  /**
   * Subscribes to a statement execution started event.
   *
   * @param \Drupal\Core\Database\Event\StatementExecutionStartEvent $event
   *   The database event.
   */
  public function onStatementExecutionStart(StatementExecutionStartEvent $event): void {
    $this->statementIdsInExecution[$event->statementObjectId] = TRUE;
    $this->countStatementStarts++;
  }

  /**
   * Subscribes to a statement execution finished event.
   *
   * @param \Drupal\Core\Database\Event\StatementExecutionEndEvent $event
   *   The database event.
   */
  public function onStatementExecutionEnd(StatementExecutionEndEvent $event): void {
    unset($this->statementIdsInExecution[$event->statementObjectId]);
    $this->countStatementEnds++;
  }

  /**
   * Subscribes to a statement execution failure event.
   *
   * @param \Drupal\Core\Database\Event\StatementExecutionFailureEvent $event
   *   The database event.
   */
  public function onStatementExecutionFailure(StatementExecutionFailureEvent $event): void {
    unset($this->statementIdsInExecution[$event->statementObjectId]);
    $this->countStatementFailures++;
  }

  /**
   * Subscribes to a TransactionBeginEvent.
   *
   * @param \Drupal\Core\Database\Event\TransactionBeginEvent $event
   *   The transaction event.
   */
  public function onTransactionBegin(TransactionBeginEvent $event): void {
    $this->keyValue->get('TransactionEventTest')->set('event_data', get_class($event) . ' ' . $event->key . ' ' . $event->target . ' ' . $event->id . '\\' . $event->name);
  }

  /**
   * Subscribes to a TransactionSavepointEvent.
   *
   * @param \Drupal\Core\Database\Event\TransactionSavepointEvent $event
   *   The transaction event.
   */
  public function onTransactionSavepoint(TransactionSavepointEvent $event): void {
    $this->keyValue->get('TransactionEventTest')->set('event_data', get_class($event) . ' ' . $event->key . ' ' . $event->target . ' ' . $event->id . '\\' . $event->name . ' stack: ' . implode(' > ', $event->stackItems));
  }

  /**
   * Subscribes to a TransactionAutoCommitEvent.
   *
   * @param \Drupal\Core\Database\Event\TransactionAutoCommitEvent $event
   *   The transaction event.
   */
  public function onTransactionAutoCommit(TransactionAutoCommitEvent $event): void {
    $this->keyValue->get('TransactionEventTest')->set('event_data', get_class($event) . ' ' . $event->key . ' ' . $event->target . ' ' . $event->id . '\\' . $event->name);
  }

  /**
   * Subscribes to a TransactionCommitEvent.
   *
   * @param \Drupal\Core\Database\Event\TransactionCommitEvent $event
   *   The transaction event.
   */
  public function onTransactionCommit(TransactionCommitEvent $event): void {
    $this->keyValue->get('TransactionEventTest')->set('event_data', get_class($event) . ' ' . $event->key . ' ' . $event->target . ' ' . $event->id . '\\' . $event->name . ' commit-type: ' . $event->type->name);
  }

  /**
   * Subscribes to a TransactionReleaseSavepointEvent.
   *
   * @param \Drupal\Core\Database\Event\TransactionReleaseSavepointEvent $event
   *   The transaction event.
   */
  public function onTransactionReleaseSavepoint(TransactionReleaseSavepointEvent $event): void {
    $this->keyValue->get('TransactionEventTest')->set('event_data', get_class($event) . ' ' . $event->key . ' ' . $event->target . ' ' . $event->id . '\\' . $event->name . ' stack: ' . implode(' > ', $event->stackItems));
  }

  /**
   * Subscribes to a TransactionRollbackEvent.
   *
   * @param \Drupal\Core\Database\Event\TransactionRollbackEvent $event
   *   The transaction event.
   */
  public function onTransactionRollback(TransactionRollbackEvent $event): void {
    $this->keyValue->get('TransactionEventTest')->set('event_data', get_class($event) . ' ' . $event->key . ' ' . $event->target . ' ' . $event->id . '\\' . $event->name);
  }

  /**
   * Subscribes to a TransactionRollbackToSavepointEvent.
   *
   * @param \Drupal\Core\Database\Event\TransactionRollbackToSavepointEvent $event
   *   The transaction event.
   */
  public function onTransactionRollbackToSavepoint(TransactionRollbackToSavepointEvent $event): void {
    $this->keyValue->get('TransactionEventTest')->set('event_data', get_class($event) . ' ' . $event->key . ' ' . $event->target . ' ' . $event->id . '\\' . $event->name . ' stack: ' . implode(' > ', $event->stackItems));
  }

}
