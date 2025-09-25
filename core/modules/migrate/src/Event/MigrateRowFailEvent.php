<?php

namespace Drupal\migrate\Event;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Row;

/**
 * Wraps a row-fail event for event listeners.
 */
class MigrateRowFailEvent extends EventBase {

  /**
   * Row object.
   *
   * @var \Drupal\migrate\Row
   */
  protected $row;

  /**
   * The exception that was thrown.
   *
   * @var \Exception
   */
  protected $exception;

  /**
   * Constructs a pre-save event object.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   Migration entity.
   * @param \Drupal\migrate\MigrateMessageInterface $message
   *   The current migrate message service.
   * @param \Drupal\migrate\Row $row
   *   Row object.
   * @param \Exception $e
   *   The exception.
   */
  public function __construct(MigrationInterface $migration, MigrateMessageInterface $message, Row $row, \Exception $e) {
    parent::__construct($migration, $message);
    $this->row = $row;
    $this->exception = $e;
  }

  /**
   * Gets the row object.
   *
   * @return \Drupal\migrate\Row
   *   The row object about to be imported.
   */
  public function getRow(): Row {
    return $this->row;
  }

  /**
   * Gets the exception that was thrown.
   *
   * @return \Exception
   *   The exception that was thrown.
   */
  public function getException(): \Exception {
    return $this->exception;
  }

}
