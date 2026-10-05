<?php

namespace Drupal\Core\Batch;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\ColumnSize;
use Drupal\Core\Database\SchemaDefinition\Index;
use Drupal\Core\Database\SchemaDefinition\PrimaryKey;
use Drupal\Core\Database\SchemaDefinition\Schema;
use Drupal\Core\Database\SchemaDefinition\SchemaDefinitionType;
use Drupal\Core\Database\SchemaDefinition\Table;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Defines the storage handler class for batches.
 */
class BatchStorage implements BatchStorageInterface {

  /**
   * The table name.
   */
  const TABLE_NAME = 'batch';

  /**
   * Constructs the database batch storage service.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfToken
   *   The CSRF token generator.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    protected Connection $connection,
    protected SessionInterface $session,
    protected CsrfTokenGenerator $csrfToken,
    protected TimeInterface $time,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function load($id) {
    // Ensure that a session is started before using the CSRF token generator.
    $this->session->start();

    $execution = $this->connection->executeEnsuringSchemaOnFailure(
      execute: function () use ($id): string|FALSE {
        return $this->connection->select('batch', 'b')
          ->fields('b', ['batch'])
          ->condition('bid', $id)
          ->condition('token', $this->csrfToken->get($id))
          ->execute()
          ->fetchField();
      },
      schema: $this->schemaDefinition(),
    );

    return $execution->isSuccessful() ? unserialize($execution->getResult()) : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function delete($id) {
    $this->connection->executeEnsuringSchemaOnFailure(
      execute: function () use ($id): void {
        $this->connection->delete('batch')
          ->condition('bid', $id)
          ->execute();
      },
      schema: $this->schemaDefinition(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function update(array $batch) {
    $this->connection->executeEnsuringSchemaOnFailure(
      execute: function () use ($batch): void {
        $this->connection->update('batch')
          ->fields(['batch' => serialize($batch)])
          ->condition('bid', $batch['id'])
          ->execute();
      },
      schema: $this->schemaDefinition(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function cleanup() {
    $this->connection->executeEnsuringSchemaOnFailure(
      execute: function (): void {
        // Cleanup the batch table and the queue for failed batches.
        $this->connection->delete('batch')
          ->condition('timestamp', $this->time->getRequestTime() - 864000, '<')
          ->execute();
      },
      schema: $this->schemaDefinition(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function create(array $batch) {
    // Ensure that a session is started before using the CSRF token generator,
    // and update the database record.
    $this->session->start();
    $this->connection->update('batch')
      ->fields([
        'token' => $this->csrfToken->get($batch['id']),
        'batch' => serialize($batch),
      ])
      ->condition('bid', $batch['id'])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): int {
    $execution = $this->connection->executeEnsuringSchemaOnFailure(
      execute: function (): int {
        return $this->connection->insert('batch')
          ->fields([
            'timestamp' => $this->time->getRequestTime(),
            'token' => '',
            'batch' => NULL,
          ])
          ->execute();
      },
      schema: $this->schemaDefinition(),
      retryAfterSchemaEnsured: TRUE,
    );

    return $execution->getResult();
  }

  /**
   * Inserts a record in the table and returns the batch id.
   *
   * @return int
   *   A batch id.
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use
   * \Drupal\Core\Database\Connection::executeEnsuringSchemaOnFailure()
   * instead.
   *
   * @see https://www.drupal.org/node/3489185
   */
  protected function doInsertBatchRecord(): int {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use \Drupal\Core\Database\Connection::executeEnsuringSchemaOnFailure() instead. See https://www.drupal.org/node/3489185', E_USER_DEPRECATED);
    return $this->connection->insert('batch')
      ->fields([
        'timestamp' => $this->time->getRequestTime(),
        'token' => '',
        'batch' => NULL,
      ])
      ->execute();
  }

  /**
   * Check if the table exists and create it if not.
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use
   * \Drupal\Core\Database\Connection::executeEnsuringSchemaOnFailure()
   * instead.
   *
   * @see https://www.drupal.org/node/3489185
   */
  protected function ensureTableExists() {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use \Drupal\Core\Database\Connection::executeEnsuringSchemaOnFailure() instead. See https://www.drupal.org/node/3489185', E_USER_DEPRECATED);
    try {
      $this->connection->schema()->createSchemaFromDefinition($this->schemaDefinition());
    }
    // If another process has already created the batch table, attempting to
    // recreate it will throw an exception. In this case just catch the
    // exception and do nothing.
    catch (DatabaseException) {
    }
    catch (\Exception) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Act on an exception when batch might be stale.
   *
   * If the table does not yet exist, that's fine, but if the table exists and
   * yet the query failed, then the batch is stale and the exception needs to
   * propagate.
   *
   * @param \Exception $e
   *   The exception.
   *
   * @throws \Exception
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use
   * \Drupal\Core\Database\Connection::executeEnsuringSchemaOnFailure()
   * instead.
   *
   * @see https://www.drupal.org/node/3489185
   */
  protected function catchException(\Exception $e) {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use \Drupal\Core\Database\Connection::executeEnsuringSchemaOnFailure() instead. See https://www.drupal.org/node/3489185', E_USER_DEPRECATED);
    if ($this->connection->schema()->tableExists(static::TABLE_NAME)) {
      throw $e;
    }
  }

  /**
   * Defines the schema for the batch table.
   *
   * @internal
   */
  public function schemaDefinition(): Schema {
    $tables[] = new Table(
      name: static::TABLE_NAME,
      description: 'Stores details about batches (processes that run in multiple HTTP requests).',
      columns: [
        Column::serial(
          name: 'bid',
          description: 'Primary Key: Unique batch ID.',
        ),
        Column::varcharAscii(
          name: 'token',
          description: "A string token generated against the current user's session id and the batch id, used to ensure that only the user who submitted the batch can effectively access it.",
          length: 64,
          notNull: TRUE,
        ),
        Column::int(
          name: 'timestamp',
          description: 'A Unix timestamp indicating when this batch was submitted for processing. Stale batches are purged at cron time.',
          notNull: TRUE,
        ),
        Column::blob(
          name: 'batch',
          description: 'A serialized array containing the processing data for the batch.',
          size: ColumnSize::Big,
          notNull: FALSE,
        ),
      ],
      primaryKey: new PrimaryKey(['bid']),
      indexes: [
        new Index(name: 'token', columns: ['token']),
      ],
    );

    return new Schema(
      type: SchemaDefinitionType::Storage,
      name: 'batch',
      tables: $tables,
    );
  }

}
