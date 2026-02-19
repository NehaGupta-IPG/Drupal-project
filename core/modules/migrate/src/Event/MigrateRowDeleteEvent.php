<?php

namespace Drupal\migrate\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Wraps a row deletion event for event listeners.
 */
class MigrateRowDeleteEvent extends Event {

  /**
   * Migration entity.
   *
   * @var MigrationInterface
   */
  protected $migration;

  /**
   * Values representing the destination ID.
   *
   * @var array
   */
  protected $destinationIdValues;

  /**
   * Constructs a row deletion event object.
   *
   * @param MigrationInterface $migration
   *   Migration entity.
   * @param array $destination_id_values
   *   Values represent the destination ID.
   */
  public function __construct(MigrationInterface $migration, $destination_id_values) {
    $this->migration = $migration;
    $this->destinationIdValues = $destination_id_values;
  }

  /**
   * Gets the migration entity.
   *
   * @return MigrationInterface
   *   The migration being rolled back.
   */
  public function getMigration() {
    return $this->migration;
  }

  /**
   * Gets the destination ID values.
   *
   * @return array
   *   The destination ID as an array.
   */
  public function getDestinationIdValues() {
    return $this->destinationIdValues;
  }

}
