<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Transaction;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Event\TransactionAutoCommitEvent;
use Drupal\Core\Database\Event\TransactionBeginEvent;
use Drupal\Core\Database\Event\TransactionCommitEvent;
use Drupal\Core\Database\Event\TransactionCommitFailureEvent;
use Drupal\Core\Database\Event\TransactionEvent;
use Drupal\Core\Database\Event\TransactionReleaseSavepointEvent;
use Drupal\Core\Database\Event\TransactionReleaseSavepointFailureEvent;
use Drupal\Core\Database\Event\TransactionRollbackEvent;
use Drupal\Core\Database\Event\TransactionRollbackFailureEvent;
use Drupal\Core\Database\Event\TransactionRollbackToSavepointEvent;
use Drupal\Core\Database\Event\TransactionRollbackToSavepointFailureEvent;
use Drupal\Core\Database\Event\TransactionSavepointEvent;
use Drupal\Core\Database\Transaction;
use Drupal\Core\Database\TransactionCommitFailedException;
use Drupal\Core\Database\TransactionException;
use Drupal\Core\Database\TransactionNameNonUniqueException;
use Drupal\Core\Database\TransactionOutOfOrderException;

/**
 * The database transaction manager base class.
 *
 * On many databases transactions cannot nest. Instead, we track nested calls
 * to transactions and collapse them into a single client transaction.
 *
 * Database drivers must implement their own class extending from this, and
 * instantiate it via their Connection::driverTransactionManager() method.
 *
 * @see \Drupal\Core\Database\Connection::driverTransactionManager()
 */
abstract class TransactionManagerBase implements TransactionManagerInterface {

  /**
   * The ID of the root Transaction object.
   *
   * The unique identifier of the first 'root' transaction object created, when
   * the stack is empty.
   *
   * Normally, during the transaction stack lifecycle only one 'root'
   * Transaction object is processed. Any post transaction callbacks are only
   * processed during its destruction. However, there are cases when there
   * could be multiple 'root' transaction objects in the stack. For example: a
   * 'root' transaction object is opened, then a DDL statement is executed in a
   * database that does not support transactional DDL, and because of that,
   * another 'root' is opened before the original one is closed.
   *
   * Keeping track of the first 'root' created allows us to process the post
   * transaction callbacks only during its destruction and not during
   * destruction of another one.
   */
  private ?string $rootId = NULL;

  /**
   * The stack of Drupal transactions currently active.
   *
   * This property is keeping track of the Transaction objects started and
   * ended as a LIFO (Last In, First Out) stack.
   *
   * The database API allows to begin transactions, add an arbitrary number of
   * additional savepoints, and release any savepoint in the sequence. When
   * this happens, the database will implicitly release all the savepoints
   * created after the one released. Given Drupal implementation of the
   * Transaction objects, we cannot force reducing the scope of the
   * corresponding Transaction savepoint objects from the manager, because they
   * live in the scope of the calling code. This stack ensures that when an
   * outlived Transaction object gets out of scope, it will not try to release
   * on the database a savepoint that no longer exists.
   *
   * Differently, rollbacks are strictly being checked for LIFO order: if a
   * rollback is requested against a savepoint that is not the last created,
   * the manager will throw a TransactionOutOfOrderException.
   *
   * The array key is the transaction's unique id, its value a StackItem.
   *
   * @var array<string,StackItem>
   */
  private array $stack = [];

  /**
   * A list of voided stack items.
   *
   * In some cases the active transaction can be automatically committed by the
   * database server (for example, MySql when a DDL statement is executed
   * during a transaction). In such cases we need to void the remaining items
   * on the stack, and we track them here.
   *
   * The array key is the transaction's unique id, its value a StackItem.
   *
   * @var array<string,StackItem>
   */
  private array $voidedItems = [];

  /**
   * A list of post-transaction callbacks.
   *
   * @var callable[]
   *
   * @see \Drupal\Core\Database\Transaction\TransactionManagerInterface::addPostTransactionCallback()
   */
  private array $postTransactionCallbacks = [];

  /**
   * The state of the underlying client connection transaction.
   *
   * Note that this is a proxy of the actual state on the database server,
   * best determined through calls to methods in this class. The actual
   * state on the database server could be different.
   */
  private ClientConnectionTransactionState $connectionTransactionState;

  /**
   * Whether the root transaction was auto-committed.
   *
   * Normally FALSE, is set to TRUE when an autocommit occurred on the
   * database engine.
   */
  private bool $isAutoCommitted = FALSE;

  /**
   * Whether to trigger warnings when unpiling a void transaction.
   *
   * Normally FALSE, is set to TRUE by specific tests checking the internal
   * state of the transaction stack.
   *
   * @internal
   */
  public bool $triggerWarningWhenUnpilingOnVoidTransaction = FALSE;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(
    protected readonly Connection $connection,
  ) {
  }

  /**
   * Destructor.
   *
   * When destructing, $stack must have been already emptied.
   */
  public function __destruct() {
    assert($this->stack === [], "Transaction \$stack was not empty. Active stack: " . $this->dumpStackItemsAsString());
  }

  /**
   * Returns the current depth of the transaction stack.
   *
   * @return int
   *   The current depth of the transaction stack.
   *
   * @todo consider making this function protected.
   *
   * @internal
   */
  public function stackDepth(): int {
    return count($this->stack());
  }

  /**
   * Returns the content of the transaction stack.
   *
   * Drivers should not override this method unless they also override the
   * $stack property.
   *
   * @return array<string,StackItem>
   *   The elements of the transaction stack.
   */
  protected function stack(): array {
    return $this->stack;
  }

  /**
   * Commits the entire transaction stack.
   *
   * @internal
   *   This method exists only to work around a bug caused by Drupal incorrectly
   *   relying on object destruction order to commit transactions. Xdebug 3.3.0
   *   changes the order of object destruction when the develop mode is enabled.
   */
  public function commitAll(): void {
    $this->connection->disableEvents(TransactionEvent::all());
    foreach (array_reverse($this->stack()) as $id => $item) {
      $this->unpile($item->name, $id);
    }
  }

  /**
   * Adds an item to the transaction stack.
   *
   * Drivers should not override this method unless they also override the
   * $stack property.
   *
   * @param string $id
   *   The id of the transaction.
   * @param \Drupal\Core\Database\Transaction\StackItem $item
   *   The stack item.
   */
  protected function addStackItem(string $id, StackItem $item): void {
    $this->stack[$id] = $item;
  }

  /**
   * Removes an item from the transaction stack.
   *
   * Drivers should not override this method unless they also override the
   * $stack property.
   *
   * @param string $id
   *   The id of the transaction.
   */
  protected function removeStackItem(string $id): void {
    unset($this->stack[$id]);
  }

  /**
   * Voids an item from the transaction stack.
   *
   * Drivers should not override this method unless they also override the
   * $stack property.
   *
   * @param string $id
   *   The id of the transaction.
   */
  protected function voidStackItem(string $id): void {
    // The item should be removed from $stack and added to $voidedItems for
    // later processing.
    if (isset($this->stack[$id])) {
      $this->voidedItems[$id] = $this->stack[$id];
    }
    $this->removeStackItem($id);
  }

  /**
   * Produces a string representation of the stack items.
   *
   * A helper method for exception messages.
   *
   * Drivers should not override this method unless they also override the
   * $stack property.
   *
   * @return string
   *   The string representation of the stack items.
   */
  protected function dumpStackItemsAsString(): string {
    if ($this->stack() === []) {
      return '*** empty ***';
    }
    return implode(' > ', $this->stackItemsAsArray());
  }

  /**
   * Produces an array representation of the stack items.
   *
   * Drivers should not override this method unless they also override the
   * $stack property.
   *
   * @return list<string>
   *   The array of stack items represented like id\name.
   */
  protected function stackItemsAsArray(): array {
    return array_map(fn(string $id, StackItem $item): string => $id . '\\' . $item->name,
      array_keys($this->stack()),
      array_values($this->stack()),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function inTransaction(): bool {
    return (bool) $this->stackDepth() && $this->getConnectionTransactionState() === ClientConnectionTransactionState::Active;
  }

  /**
   * {@inheritdoc}
   */
  public function currentTransactionId(): ?string {
    return $this->inTransaction() ? array_key_last($this->stack) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function currentTransactionName(): ?string {
    return $this->inTransaction() ? end($this->stack)->name : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function push(string $name = ''): Transaction {
    if (!$this->inTransaction()) {
      // If there is no transaction active, name the transaction
      // 'drupal_transaction'.
      $name = 'drupal_transaction';
    }
    elseif (!$name) {
      // Within transactions, savepoints are used. Each savepoint requires a
      // name. So if no name is present we need to create one.
      $name = 'savepoint_' . $this->stackDepth();
    }

    if ($this->has($name)) {
      throw new TransactionNameNonUniqueException("A transaction named {$name} is already in use. Active stack: " . $this->dumpStackItemsAsString());
    }

    // Define a unique ID for the transaction.
    $id = uniqid('', TRUE);

    // Do the client-level processing.
    if ($this->stackDepth() === 0) {
      $this->beginClientTransaction();
      $this->setConnectionTransactionState(ClientConnectionTransactionState::Active);
      $this->addStackItem($id, new StackItem($name, StackItemType::Root));
      // Only set ::rootId if there's not one set already, which may happen in
      // case of broken transactions.
      if ($this->rootId === NULL) {
        $this->rootId = $id;
        $this->isAutoCommitted = FALSE;
      }
      if ($this->connection->isEventEnabled(TransactionBeginEvent::class)) {
        $this->connection->dispatchEvent(new TransactionBeginEvent(
          $this->connection->getKey(),
          $this->connection->getTarget(),
          $id,
          $name,
          $this->getConnectionTransactionState(),
          $this->stackItemsAsArray(),
        ));
      }
    }
    else {
      // If we're already in a Drupal transaction then we want to create a
      // database savepoint, rather than try to begin another database
      // transaction.
      $this->addClientSavepoint($name);
      $this->addStackItem($id, new StackItem($name, StackItemType::Savepoint));
      if ($this->connection->isEventEnabled(TransactionSavepointEvent::class)) {
        $this->connection->dispatchEvent(new TransactionSavepointEvent(
          $this->connection->getKey(),
          $this->connection->getTarget(),
          $id,
          $name,
          $this->getConnectionTransactionState(),
          $this->stackItemsAsArray(),
        ));
      }
    }

    // Actually return a new Transaction object.
    return new Transaction($this->connection, $name, $id);
  }

  /**
   * {@inheritdoc}
   */
  public function purge(string $name, string $id): void {
    // If this is a 'root' transaction, and it is voided (that is, no longer in
    // the stack), then the transaction on the database is no longer active. An
    // action such as a commit, a release savepoint, a rollback, or a DDL
    // statement, was executed that terminated the database transaction. So, we
    // can process the post transaction callbacks.
    if (!isset($this->stack()[$id]) && isset($this->voidedItems[$id]) && $this->rootId === $id) {
      if ($this->isAutoCommitted) {
        if ($this->connection->isEventEnabled(TransactionCommitEvent::class)) {
          $this->connection->dispatchEvent(new TransactionCommitEvent(
            $this->connection->getKey(),
            $this->connection->getTarget(),
            $id,
            $name,
            $this->getConnectionTransactionState(),
            $this->stackItemsAsArray(),
            CommitType::AutomaticUponDDL,
          ));
        }
        $this->isAutoCommitted = FALSE;
      }
      $this->processPostTransactionCallbacks();
      $this->rootId = NULL;
      unset($this->voidedItems[$id]);
      return;
    }

    // If the $id does not correspond to the one in the stack for that $name,
    // we are facing an orphaned Transaction object (for example in case of a
    // DDL statement breaking an active transaction). That should be listed in
    // $voidedItems, so we can remove it from there.
    if (!isset($this->stack()[$id]) || $this->stack()[$id]->name !== $name) {
      unset($this->voidedItems[$id]);
      return;
    }

    // When we get here, the transaction (or savepoint) is still active on the
    // database. We can unpile it, and if we are left with no more items in the
    // stack, we can also process the post transaction callbacks.
    $this->commit($name, $id, CommitType::ImplicitOnDestruct);
    $this->removeStackItem($id);
    if ($this->rootId === $id) {
      $this->processPostTransactionCallbacks();
      $this->rootId = NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function unpile(string $name, string $id): void {
    // If the transaction was voided, we cannot unpile. Skip but trigger a user
    // warning if requested. If an autocommit happened, emit the
    // TransactionCommitEvent.
    if ($this->getConnectionTransactionState() === ClientConnectionTransactionState::Voided) {
      if ($this->triggerWarningWhenUnpilingOnVoidTransaction) {
        trigger_error('Transaction::commitOrRelease() was not processed because a prior execution of a DDL statement already committed the transaction.', E_USER_WARNING);
      }
      if ($this->isAutoCommitted) {
        if ($this->connection->isEventEnabled(TransactionCommitEvent::class)) {
          $this->connection->dispatchEvent(new TransactionCommitEvent(
            $this->connection->getKey(),
            $this->connection->getTarget(),
            $id,
            $name,
            $this->getConnectionTransactionState(),
            $this->stackItemsAsArray(),
            CommitType::AutomaticUponDDL,
          ));
        }
        $this->isAutoCommitted = FALSE;
      }
      return;
    }

    // If there is no $id to commit, or if $id does not correspond to the one
    // in the stack for that $name, the commit is out of order.
    if (!isset($this->stack()[$id]) || $this->stack()[$id]->name !== $name) {
      throw new TransactionOutOfOrderException("Error attempting commit of {$id}\\{$name}. Active stack: " . $this->dumpStackItemsAsString());
    }

    // Commit the transaction.
    $this->commit($name, $id, CommitType::Explicit);

    // Void the transaction stack item.
    $this->voidStackItem($id);
  }

  /**
   * Commits a Drupal transaction.
   *
   * @param string $name
   *   The name of the transaction.
   * @param string $id
   *   The id of the transaction.
   * @param \Drupal\Core\Database\Transaction\CommitType|null $commitType
   *   The type of commit.
   *
   * @throws \Drupal\Core\Database\TransactionOutOfOrderException
   *   If a Drupal Transaction with the specified name does not exist.
   * @throws \Drupal\Core\Database\TransactionCommitFailedException
   *   If the commit of the root transaction failed.
   */
  protected function commit(string $name, string $id, ?CommitType $commitType = NULL): void {
    if ($this->getConnectionTransactionState() !== ClientConnectionTransactionState::Active) {
      // The stack got corrupted.
      throw new TransactionOutOfOrderException("Transaction {$id}\\{$name} is out of order. Active stack: " . $this->dumpStackItemsAsString());
    }

    // If we are not releasing the last savepoint but an earlier one, or
    // committing a root transaction while savepoints are active, all
    // subsequent savepoints will be released as well. The stack must be
    // diminished accordingly.
    while (($i = array_key_last($this->stack())) != $id) {
      $this->voidStackItem((string) $i);
    }

    if ($this->stackDepth() > 1 && $this->stack()[$id]->type === StackItemType::Savepoint) {
      // Release the client transaction savepoint in case the Drupal
      // transaction is not a root one.
      try {
        if (!$this->releaseClientSavepoint($name)) {
          throw new TransactionCommitFailedException("Release savepoint $name failed");
        }
        $this->removeStackItem($id);
        if ($this->connection->isEventEnabled(TransactionReleaseSavepointEvent::class)) {
          $this->connection->dispatchEvent(new TransactionReleaseSavepointEvent(
            $this->connection->getKey(),
            $this->connection->getTarget(),
            $id,
            $name,
            $this->getConnectionTransactionState(),
            $this->stackItemsAsArray(),
            $commitType,
          ));
        }
      }
      catch (\Exception $e) {
        $this->setConnectionTransactionState(ClientConnectionTransactionState::CommitFailed);
        $this->voidClientTransaction();
        if ($this->connection->isEventEnabled(TransactionReleaseSavepointFailureEvent::class)) {
          $this->connection->dispatchEvent(new TransactionReleaseSavepointFailureEvent(
            $this->connection->getKey(),
            $this->connection->getTarget(),
            $id,
            $name,
            $this->getConnectionTransactionState(),
            $this->stackItemsAsArray(),
            get_class($e),
            $e->getMessage(),
            $commitType,
          ));
        }
      }
    }
    elseif ($this->stackDepth() === 1 && $this->stack()[$id]->type === StackItemType::Root) {
      // If this was the root Drupal transaction, we can commit the client
      // transaction.
      try {
        $this->processRootCommit();
        $this->voidStackItem($id);
        if ($this->connection->isEventEnabled(TransactionCommitEvent::class)) {
          $this->connection->dispatchEvent(new TransactionCommitEvent(
            $this->connection->getKey(),
            $this->connection->getTarget(),
            $id,
            $name,
            $this->getConnectionTransactionState(),
            $this->stackItemsAsArray(),
            $commitType,
          ));
        }
      }
      catch (\Exception $e) {
        $this->voidClientTransaction();
        if ($this->connection->isEventEnabled(TransactionCommitFailureEvent::class)) {
          $this->connection->dispatchEvent(new TransactionCommitFailureEvent(
            $this->connection->getKey(),
            $this->connection->getTarget(),
            $id,
            $name,
            $this->getConnectionTransactionState(),
            $this->stackItemsAsArray(),
            get_class($e),
            $e->getMessage(),
            $commitType,
          ));
        }
        throw $e;
      }
    }
    else {
      // The stack got corrupted.
      throw new TransactionOutOfOrderException("Transaction {$id}/{$name} is out of order. Active stack: " . $this->dumpStackItemsAsString());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function rollback(string $name, string $id): void {
    // If the transaction was voided, we cannot rollback. Fail silently but
    // trigger a user warning.
    if ($this->getConnectionTransactionState() === ClientConnectionTransactionState::Voided) {
      $this->connectionTransactionState = ClientConnectionTransactionState::RollbackFailed;
      $message = 'Transaction::rollBack() failed because of a prior execution of a DDL statement.';
      if ($this->connection->isEventEnabled(TransactionRollbackFailureEvent::class)) {
        $this->connection->dispatchEvent(new TransactionRollbackFailureEvent(
          $this->connection->getKey(),
          $this->connection->getTarget(),
          $id,
          $name,
          $this->getConnectionTransactionState(),
          $this->stackItemsAsArray(),
          get_class($this),
          $message,
        ));
      }
      trigger_error($message, E_USER_WARNING);
      return;
    }

    // Rolled back item should match the last one in stack.
    if ($id != array_key_last($this->stack()) || $name !== $this->stack()[$id]->name) {
      throw new TransactionOutOfOrderException("Error attempting rollback of {$id}\\{$name}. Active stack: " . $this->dumpStackItemsAsString());
    }

    if ($this->getConnectionTransactionState() === ClientConnectionTransactionState::Active) {
      if ($this->stackDepth() > 1 && $this->stack()[$id]->type === StackItemType::Savepoint) {
        // Rollback the client transaction to the savepoint when the Drupal
        // transaction is not a root one. Then, release the savepoint too. The
        // client connection remains active.
        try {
          if (!$this->rollbackClientSavepoint($name)) {
            throw new TransactionException("Rollback to savepoint $name failed");
          }
          if (!$this->releaseClientSavepoint($name)) {
            throw new TransactionException("Release savepoint $name failed");
          }
          // The Transaction object remains open, and when it will get
          // destructed no commit should happen. Void the stack item.
          $this->voidStackItem($id);
          if ($this->connection->isEventEnabled(TransactionRollbackToSavepointEvent::class)) {
            $this->connection->dispatchEvent(new TransactionRollbackToSavepointEvent(
              $this->connection->getKey(),
              $this->connection->getTarget(),
              $id,
              $name,
              $this->getConnectionTransactionState(),
              $this->stackItemsAsArray(),
            ));
          }
        }
        catch (\Exception $e) {
          $this->setConnectionTransactionState(ClientConnectionTransactionState::RollbackFailed);
          $this->voidClientTransaction();
          if ($this->connection->isEventEnabled(TransactionRollbackToSavepointFailureEvent::class)) {
            $this->connection->dispatchEvent(new TransactionRollbackToSavepointFailureEvent(
              $this->connection->getKey(),
              $this->connection->getTarget(),
              $id,
              $name,
              $this->getConnectionTransactionState(),
              $this->stackItemsAsArray(),
              get_class($e),
              $e->getMessage(),
            ));
          }
        }
      }
      elseif ($this->stackDepth() === 1 && $this->stack()[$id]->type === StackItemType::Root) {
        // If this was the root Drupal transaction, we can rollback the client
        // transaction. The transaction is closed.
        try {
          $this->processRootRollback();
          // The Transaction object remains open, and when it will get
          // destructed no commit should happen. Void the stack item.
          $this->voidStackItem($id);
          if ($this->connection->isEventEnabled(TransactionRollbackEvent::class)) {
            $this->connection->dispatchEvent(new TransactionRollbackEvent(
              $this->connection->getKey(),
              $this->connection->getTarget(),
              $id,
              $name,
              $this->getConnectionTransactionState(),
              $this->stackItemsAsArray(),
            ));
          }
        }
        catch (\Exception $e) {
          $this->voidClientTransaction();
          if ($this->connection->isEventEnabled(TransactionRollbackFailureEvent::class)) {
            $this->connection->dispatchEvent(new TransactionRollbackFailureEvent(
              $this->connection->getKey(),
              $this->connection->getTarget(),
              $id,
              $name,
              $this->getConnectionTransactionState(),
              $this->stackItemsAsArray(),
              get_class($e),
              $e->getMessage(),
            ));
          }
        }
      }
      else {
        // The stack got corrupted.
        throw new TransactionOutOfOrderException("Error attempting rollback of {$id}\\{$name}. Active stack: " . $this->dumpStackItemsAsString());
      }
      return;
    }

    // The stack got corrupted.
    throw new TransactionOutOfOrderException("Error attempting rollback of {$id}\\{$name}. Active stack: " . $this->dumpStackItemsAsString());
  }

  /**
   * {@inheritdoc}
   */
  public function addPostTransactionCallback(callable $callback): void {
    if (!$this->inTransaction()) {
      throw new \LogicException('Root transaction end callbacks can only be added when there is an active transaction.');
    }
    $this->postTransactionCallbacks[] = $callback;
  }

  /**
   * {@inheritdoc}
   */
  public function has(string $name): bool {
    foreach ($this->stack() as $item) {
      if ($item->name === $name) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Sets the state of the client connection transaction.
   *
   * Note that this is a proxy of the actual state on the database server,
   * best determined through calls to methods in this class. The actual
   * state on the database server could be different.
   *
   * Drivers should not override this method unless they also override the
   * $connectionTransactionState property.
   *
   * @param \Drupal\Core\Database\Transaction\ClientConnectionTransactionState $state
   *   The state of the client connection.
   */
  protected function setConnectionTransactionState(ClientConnectionTransactionState $state): void {
    $this->connectionTransactionState = $state;
  }

  /**
   * Gets the state of the client connection transaction.
   *
   * Note that this is a proxy of the actual state on the database server,
   * best determined through calls to methods in this class. The actual
   * state on the database server could be different.
   *
   * Drivers should not override this method unless they also override the
   * $connectionTransactionState property.
   *
   * @return \Drupal\Core\Database\Transaction\ClientConnectionTransactionState
   *   The state of the client connection.
   */
  protected function getConnectionTransactionState(): ClientConnectionTransactionState {
    return $this->connectionTransactionState;
  }

  /**
   * Processes the root transaction rollback.
   */
  protected function processRootRollback(): void {
    $clientRollback = $this->rollbackClientTransaction();
    if (!$clientRollback) {
      $this->setConnectionTransactionState(ClientConnectionTransactionState::RollbackFailed);
      throw new TransactionException('Transaction rollback failed');
    }
    $this->setConnectionTransactionState(ClientConnectionTransactionState::RolledBack);
  }

  /**
   * Processes the root transaction commit.
   *
   * @throws \Drupal\Core\Database\TransactionCommitFailedException
   *   If the commit of the root transaction failed.
   */
  protected function processRootCommit(): void {
    $clientCommit = $this->commitClientTransaction();
    if (!$clientCommit) {
      $this->setConnectionTransactionState(ClientConnectionTransactionState::CommitFailed);
      throw new TransactionCommitFailedException('Transaction commit failed');
    }
    $this->setConnectionTransactionState(ClientConnectionTransactionState::Committed);
  }

  /**
   * Processes the post-transaction callbacks.
   */
  protected function processPostTransactionCallbacks(): void {
    if (!empty($this->postTransactionCallbacks)) {
      $callbacks = $this->postTransactionCallbacks;
      $this->postTransactionCallbacks = [];
      foreach ($callbacks as $callback) {
        call_user_func($callback, $this->getConnectionTransactionState() === ClientConnectionTransactionState::Committed || $this->getConnectionTransactionState() === ClientConnectionTransactionState::Voided);
      }
    }
  }

  /**
   * Begins a transaction on the client connection.
   *
   * @return bool
   *   Returns TRUE on success or FALSE on failure.
   */
  abstract protected function beginClientTransaction(): bool;

  /**
   * Adds a savepoint on the client transaction.
   *
   * This is a generic implementation. Drivers should override this method
   * to use a method specific for their client connection.
   *
   * @param string $name
   *   The name of the savepoint.
   *
   * @return bool
   *   Returns TRUE on success or FALSE on failure.
   */
  protected function addClientSavepoint(string $name): bool {
    $this->connection->query('SAVEPOINT ' . $name);
    return TRUE;
  }

  /**
   * Rolls back to a savepoint on the client transaction.
   *
   * This is a generic implementation. Drivers should override this method
   * to use a method specific for their client connection.
   *
   * @param string $name
   *   The name of the savepoint.
   *
   * @return bool
   *   Returns TRUE on success or FALSE on failure.
   */
  protected function rollbackClientSavepoint(string $name): bool {
    $this->connection->query('ROLLBACK TO SAVEPOINT ' . $name);
    return TRUE;
  }

  /**
   * Releases a savepoint on the client transaction.
   *
   * This is a generic implementation. Drivers should override this method
   * to use a method specific for their client connection.
   *
   * @param string $name
   *   The name of the savepoint.
   *
   * @return bool
   *   Returns TRUE on success or FALSE on failure.
   */
  protected function releaseClientSavepoint(string $name): bool {
    $this->connection->query('RELEASE SAVEPOINT ' . $name);
    return TRUE;
  }

  /**
   * Rolls back a client transaction.
   *
   * @return bool
   *   Returns TRUE on success or FALSE on failure.
   */
  abstract protected function rollbackClientTransaction(): bool;

  /**
   * Commits a client transaction.
   *
   * @return bool
   *   Returns TRUE on success or FALSE on failure.
   */
  abstract protected function commitClientTransaction(): bool;

  /**
   * {@inheritdoc}
   */
  public function voidClientTransaction(): void {
    while ($i = array_key_last($this->stack())) {
      $this->voidStackItem((string) $i);
    }
    if ($this->getConnectionTransactionState() === ClientConnectionTransactionState::Active) {
      $this->setConnectionTransactionState(ClientConnectionTransactionState::Voided);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function handleAutomaticCommitUponDDL(): void {
    if ($this->connection->isEventEnabled(TransactionAutoCommitEvent::class)) {
      $i = array_key_first($this->stack);
      assert($this->stack[$i]->type === StackItemType::Root);
      $this->connection->dispatchEvent(new TransactionAutoCommitEvent(
        $this->connection->getKey(),
        $this->connection->getTarget(),
        $i,
        $this->stack[$i]->name,
        $this->getConnectionTransactionState(),
        $this->stackItemsAsArray(),
      ));
    }
    $this->voidClientTransaction();
    $this->isAutoCommitted = TRUE;
  }

}
