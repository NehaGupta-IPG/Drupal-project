<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Event;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\SchemaDefinition\Schema as SchemaDefinition;
use Drupal\Core\Database\SchemaException;

/**
 * Represents the execution of a method enforcing an indicated schema exists.
 */
final class ExecuteMethodEnsuringSchemaEvent extends DatabaseEvent {

  /**
   * Value returned by the execution of the callback.
   */
  private mixed $callbackExecutionResult;

  /**
   * Indicates the initial callback execution state.
   */
  private bool|\Exception $callbackExecutionState = FALSE;

  /**
   * Indicates the retried callback execution state.
   */
  private bool|\Exception $callbackRetryExecutionState = FALSE;

  /**
   * Indicates the schema creation state.
   */
  private bool|SchemaException $schemaCreationState = FALSE;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Closure $execute
   *   The callback to be executed.
   * @param array<string,array<string,mixed>>|\Drupal\Core\Database\SchemaDefinition\Schema|\Closure $schema
   *   A database schema specification, either as an array with table name
   *   as key and schema array as value, or as a schema definition object; or
   *   a callback to be executed. The callback must return TRUE if the
   *   database was changed, FALSE if it was executed but did not change the
   *   database, or throw an exception.
   * @param bool $retryAfterSchemaEnsured
   *   (Optional) If TRUE, the callback is executed again after the first
   *   execution failed, and the schema enforcement was successful. Defaults to
   *   FALSE.
   */
  public function __construct(
    public readonly Connection $connection,
    public readonly \Closure $execute,
    public readonly array|SchemaDefinition|\Closure $schema,
    public readonly bool $retryAfterSchemaEnsured = FALSE,
  ) {
    parent::__construct();
  }

  /**
   * Stores the value returned by the execution of the callback.
   *
   * @param mixed $result
   *   The value returned by the execution of the callback.
   */
  public function setResult(mixed $result): void {
    $this->callbackExecutionResult = $result;
  }

  /**
   * Gets the value returned by the execution of the callback.
   *
   * @return mixed
   *   The value returned by the execution of the callback.
   */
  public function getResult(): mixed {
    assert(isset($this->callbackExecutionResult), __METHOD__ . '() was called before successful execution of the callback');
    return $this->callbackExecutionResult;
  }

  /**
   * Stores the outcome of the initial execution of the callback.
   *
   * @param true|\Exception $outcome
   *   The outcome of the execution of the callback.
   */
  public function setCallbackExecutionState(TRUE|\Exception $outcome): void {
    $this->callbackExecutionState = $outcome;
  }

  /**
   * Stores the outcome of the retried execution of the callback.
   *
   * @param true|\Exception $outcome
   *   The outcome of the execution of the callback.
   */
  public function setCallbackRetryExecutionState(TRUE|\Exception $outcome): void {
    $this->callbackRetryExecutionState = $outcome;
  }

  /**
   * Stores the outcome of the schema creation.
   *
   * @param true|\Drupal\Core\Database\SchemaException $outcome
   *   The outcome of the schema creation.
   */
  public function setSchemaCreationState(TRUE|SchemaException $outcome): void {
    $this->schemaCreationState = $outcome;
  }

  /**
   * Get the overall success of the execution of the callback.
   *
   * @return bool
   *   The success of the execution of the callback.
   */
  public function isSuccessful(): bool {
    return $this->callbackExecutionState === TRUE || $this->callbackRetryExecutionState === TRUE;
  }

  /**
   * Gets the outcome of the initial execution of the callback.
   *
   * @return bool|\Exception
   *   The outcome of the execution of the callback.
   */
  public function getCallbackExecutionState(): bool|\Exception {
    return $this->callbackExecutionState;
  }

  /**
   * Gets the outcome of the retried execution of the callback.
   *
   * @return bool|\Exception
   *   The outcome of the execution of the callback.
   */
  public function getCallbackRetryExecutionState(): bool|\Exception {
    return $this->callbackRetryExecutionState;
  }

  /**
   * Gets the outcome of the schema creation.
   *
   * @return bool|\Drupal\Core\Database\SchemaException
   *   The outcome of the schema creation.
   */
  public function getSchemaCreationState(): bool|SchemaException {
    return $this->schemaCreationState;
  }

}
