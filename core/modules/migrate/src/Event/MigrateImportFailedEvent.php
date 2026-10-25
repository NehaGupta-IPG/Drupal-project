<?php

declare(strict_types=1);

namespace Drupal\migrate\Event;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateMessageInterface;

/**
 * Wraps an import failed event for event listeners.
 */
class MigrateImportFailedEvent extends EventBase {

  /**
   * Constructs an import failed event object.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   Migration entity.
   * @param \Drupal\migrate\MigrateMessageInterface $message
   *   The current migrate message service.
   * @param \Throwable|null $exception
   *   The exception that was thrown if applicable.
   */
  public function __construct(MigrationInterface $migration, MigrateMessageInterface $message, protected \Throwable|null $exception = NULL) {
    parent::__construct($migration, $message);
  }

  /**
   * Get the exception that was thrown.
   */
  public function getException(): \Throwable|null {
    return $this->exception;
  }

}
