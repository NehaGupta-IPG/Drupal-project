<?php

namespace Drupal\migrate\Event;

use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;

/**
 * Wraps a pre-save event for event listeners.
 */
class MigratePreRowSaveEvent extends EventBase {

  /**
   * Row object.
   *
   * @var Row
   */
  protected $row;

  /**
   * Constructs a pre-save event object.
   *
   * @param MigrationInterface $migration
   *   Migration entity.
   * @param MigrateMessageInterface $message
   *   The current migrate message service.
   * @param Row $row
   *   The current row.
   */
  public function __construct(MigrationInterface $migration, MigrateMessageInterface $message, Row $row) {
    parent::__construct($migration, $message);
    $this->row = $row;
  }

  /**
   * Gets the row object.
   *
   * @return Row
   *   The row object about to be imported.
   */
  public function getRow() {
    return $this->row;
  }

}
