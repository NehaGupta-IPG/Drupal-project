<?php

namespace Drupal\migrate\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Wraps a pre- or post-rollback event for event listeners.
 */
class MigrateRollbackEvent extends Event {

  /**
   * Migration entity.
   *
   * @var MigrationInterface
   */
  protected $migration;

  /**
   * Constructs a rollback event object.
   *
   * @param MigrationInterface $migration
   *   Migration entity.
   */
  public function __construct(MigrationInterface $migration) {
    $this->migration = $migration;
  }

  /**
   * Gets the migration entity.
   *
   * @return MigrationInterface
   *   The migration entity involved.
   */
  public function getMigration() {
    return $this->migration;
  }

}
