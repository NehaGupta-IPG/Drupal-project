<?php

namespace Drupal\migrate\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Base class for migration events.
 */
class EventBase extends Event {

  /**
   * The migration.
   *
   * @var MigrationInterface
   */
  protected $migration;

  /**
   * The current message service.
   *
   * @var MigrateMessageInterface
   */
  protected $message;

  /**
   * Constructs a Migrate event object.
   *
   * @param MigrationInterface $migration
   *   The migration being run.
   * @param MigrateMessageInterface $message
   *   The Migrate message service.
   */
  public function __construct(MigrationInterface $migration, MigrateMessageInterface $message) {
    $this->migration = $migration;
    $this->message = $message;
  }

  /**
   * Gets the migration.
   *
   * @return MigrationInterface
   *   The migration being run.
   */
  public function getMigration() {
    return $this->migration;
  }

  /**
   * Logs a message using the Migrate message service.
   *
   * @param string $message
   *   The message to log.
   * @param string $type
   *   The type of message, for example: status or warning.
   */
  public function logMessage($message, $type = 'status') {
    $this->message->display($message, $type);
  }

}
