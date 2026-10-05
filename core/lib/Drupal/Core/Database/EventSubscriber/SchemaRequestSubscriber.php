<?php

declare(strict_types=1);

namespace Drupal\Core\Database\EventSubscriber;

use Drupal\Core\Database\Event\ExecuteMethodEnsuringSchemaEvent;
use Drupal\Core\Database\Exception\SchemaCreationFailureException;
use Drupal\Core\Database\SchemaDefinition\Schema as SchemaDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Database\DatabaseException;

/**
 * Response subscriber to schema handling requests.
 *
 * @internal
 */
class SchemaRequestSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ExecuteMethodEnsuringSchemaEvent::class => 'onExecuteMethodEnsuringSchema',
    ];
  }

  /**
   * Processes a request to execute a callback with schema enforcement.
   *
   * @param \Drupal\Core\Database\Event\ExecuteMethodEnsuringSchemaEvent $event
   *   The event to process.
   */
  public function onExecuteMethodEnsuringSchema(ExecuteMethodEnsuringSchemaEvent $event): void {
    try {
      $event->setResult(($event->execute)());
      $event->setCallbackExecutionState(TRUE);
      return;
    }
    catch (\Exception $e) {
      // If there was an exception, try to create the schema.
      $event->setCallbackExecutionState($e);
      $schemaChanged = $this->processSchema($event);
      // Now that the schema has been created, try again if requested.
      if ($event->retryAfterSchemaEnsured && $schemaChanged) {
        try {
          $event->setResult(($event->execute)());
          $event->setCallbackRetryExecutionState(TRUE);
        }
        catch (\Exception $e) {
          $event->setCallbackRetryExecutionState($e);
          return;
        }
      }
    }
  }

  /**
   * Processes the schema creating the missing tables.
   *
   * @param \Drupal\Core\Database\Event\ExecuteMethodEnsuringSchemaEvent $event
   *   The event to process.
   *
   * @return bool
   *   TRUE if a schema change happened, FALSE otherwise.
   */
  private function processSchema(ExecuteMethodEnsuringSchemaEvent $event): bool {
    $schemaChanged = FALSE;
    // Either process the schema definition, or execute the schema callback.
    if (is_array($event->schema) || $event->schema instanceof SchemaDefinition) {
      $tables = $event->schema;
      if ($tables instanceof SchemaDefinition) {
        $tables = [];
        foreach ($event->schema->tables as $table) {
          $tables[$table->name] = $table->toArray();
        }
      }
      foreach ($tables as $name => $definition) {
        if ($event->connection->schema()->tableExists($name)) {
          continue;
        }
        try {
          $event->connection->schema()->createTable($name, $definition);
          $event->setSchemaCreationState(TRUE);
          $schemaChanged = TRUE;
        }
        // In a race condition, if another process has already created the
        // table, attempting to create it will throw an exception. In this
        // case just assume the schema was changed here.
        catch (DatabaseException $e) {
          if (!$event->connection->schema()->tableExists($name)) {
            $exception = new SchemaCreationFailureException(sprintf('Failed creation of table {%s}', $name), 0, $e);
            $event->setSchemaCreationState($exception);
            return FALSE;
          }
          $event->setSchemaCreationState(TRUE);
          $schemaChanged = TRUE;
        }
      }
    }
    else {
      try {
        $schemaChanged = ($event->schema)();
        $event->setSchemaCreationState($schemaChanged);
      }
      catch (DatabaseException $e) {
        $exception = new SchemaCreationFailureException(sprintf('Schema creation callback failed: %s', $e->getMessage()), 0, $e);
        $event->setSchemaCreationState($exception);
        return FALSE;
      }
    }
    return $schemaChanged;
  }

}
