<?php

namespace Drupal\Core\Flood;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the database flood backend. This is the default Drupal backend.
 */
class DatabaseBackend implements FloodInterface, PrefixFloodInterface {


  /**
   * The database table name.
   */
  const TABLE_NAME = 'flood';

  /**
   * Construct the DatabaseBackend.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection which will be used to store the flood event
   *   information.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack used to retrieve the current request.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    protected Connection $connection,
    protected RequestStack $requestStack,
    protected TimeInterface $time,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function register($name, $window = 3600, $identifier = NULL) {
    if (!isset($identifier)) {
      $identifier = $this->requestStack->getCurrentRequest()->getClientIp();
    }
    $this->connection->executeAndCreateTableIfNeeded(
      static::TABLE_NAME,
      fn() => $this->connection->schema()->createTable(static::TABLE_NAME, $this->schemaDefinition()),
      fn() => $this->doInsert($name, $window, $identifier),
    );
  }

  /**
   * Inserts an event into the flood table.
   *
   * @param string $name
   *   The name of an event.
   * @param int $window
   *   Number of seconds before this event expires.
   * @param string $identifier
   *   Unique identifier of the current user.
   *
   * @see \Drupal\Core\Flood\DatabaseBackend::register
   */
  protected function doInsert($name, $window, $identifier) {
    $this->connection->insert(static::TABLE_NAME)
      ->fields([
        'event' => $name,
        'identifier' => $identifier,
        'timestamp' => $this->time->getRequestTime(),
        'expiration' => $this->time->getRequestTime() + $window,
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function clear($name, $identifier = NULL) {
    return $this->connection->executeAndCreateTableIfNeeded(
      static::TABLE_NAME,
      fn() => $this->connection->schema()->createTable(static::TABLE_NAME, $this->schemaDefinition()),
      function () use ($name, $identifier) {
        if (!isset($identifier)) {
          $identifier = $this->requestStack->getCurrentRequest()->getClientIp();
        }
        try {
          $this->connection->delete(static::TABLE_NAME)
            ->condition('event', $name)
            ->condition('identifier', $identifier)
            ->execute();
        }
        catch (\Exception $e) {
          $this->catchException($e);
        }
      },
    );
  }

  /**
   * {@inheritdoc}
   */
  public function clearByPrefix(string $name, string $prefix): void {
    $this->connection->executeAndCreateTableIfNeeded(
      static::TABLE_NAME,
      fn() => $this->connection->schema()->createTable(static::TABLE_NAME, $this->schemaDefinition()),
      function () use ($name, $prefix) {
        try {
          $this->connection->delete(static::TABLE_NAME)
            ->condition('event', $name)
            ->condition('identifier', $prefix . '-%', 'LIKE')
            ->execute();
        }
        catch (\Exception $e) {
          $this->catchException($e);
        }
      },
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed($name, $threshold, $window = 3600, $identifier = NULL) {
    return $this->connection->executeAndCreateTableIfNeeded(
      static::TABLE_NAME,
      fn() => $this->connection->schema()->createTable(static::TABLE_NAME, $this->schemaDefinition()),
      function () use ($name, $threshold, $window, $identifier) {
        if (!isset($identifier)) {
          $identifier = $this->requestStack->getCurrentRequest()->getClientIp();
        }
        $number = $this->connection->select(static::TABLE_NAME, 'f')
          ->condition('event', $name)
          ->condition('identifier', $identifier)
          ->condition('timestamp', $this->time->getRequestTime() - $window, '>')
          ->countQuery()
          ->execute()
          ->fetchField();
        return ($number < $threshold);
      },
    );
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    return $this->connection->executeAndCreateTableIfNeeded(
      static::TABLE_NAME,
      fn() => $this->connection->schema()->createTable(static::TABLE_NAME, $this->schemaDefinition()),
      function () {
        try {
          $this->connection->delete(static::TABLE_NAME)
            ->condition('expiration', $this->time->getRequestTime(), '<')
            ->execute();
        }
        catch (\Exception $e) {
          $this->catchException($e);
        }
      },
    );
  }

  /**
   * Act on an exception when flood might be stale.
   *
   * If the table does not yet exist, that's fine, but if the table exists and
   * yet the query failed, then the flood is stale and the exception needs to
   * propagate.
   *
   * @param \Exception $e
   *   The exception.
   *
   * @throws \Exception
   */
  protected function catchException(\Exception $e) {
    if ($this->connection->schema()->tableExists(static::TABLE_NAME)) {
      throw $e;
    }
  }

  /**
   * Defines the schema for the flood table.
   *
   * @internal
   */
  public function schemaDefinition() {
    return [
      'description' => 'Flood controls the threshold of events, such as the number of contact attempts.',
      'fields' => [
        'fid' => [
          'description' => 'Unique flood event ID.',
          'type' => 'serial',
          'not null' => TRUE,
        ],
        'event' => [
          'description' => 'Name of event (e.g. contact).',
          'type' => 'varchar_ascii',
          'length' => 64,
          'not null' => TRUE,
          'default' => '',
        ],
        'identifier' => [
          'description' => 'Identifier of the visitor, such as an IP address or hostname.',
          'type' => 'varchar_ascii',
          'length' => 128,
          'not null' => TRUE,
          'default' => '',
        ],
        'timestamp' => [
          'description' => 'Timestamp of the event.',
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'size' => 'big',
        ],
        'expiration' => [
          'description' => 'Expiration timestamp. Expired events are purged on cron run.',
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'size' => 'big',
        ],
      ],
      'primary key' => ['fid'],
      'indexes' => [
        'allow' => ['event', 'identifier', 'timestamp'],
        'purge' => ['expiration'],
      ],
    ];
  }

}
