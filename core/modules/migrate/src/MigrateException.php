<?php

namespace Drupal\migrate;

use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Defines the migrate exception class.
 */
class MigrateException extends \Exception {

  /**
   * The level of the error being reported.
   *
   * The value is a MigrationInterface::MESSAGE_* constant.
   *
   * @var int
   *
   * @see \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $level;

  /**
   * The status to record in the map table for the current item.
   *
   * The value is a MigrateIdMapInterface::STATUS_* constant.
   *
   * @var int
   *
   * @see \Drupal\migrate\Plugin\MigrateIdMapInterface
   */
  protected $status;

  public function __construct($message = '', $code = 0, ?\Throwable $previous = NULL, $level = MigrationInterface::MESSAGE_ERROR, $status = MigrateIdMapInterface::STATUS_FAILED) {
    $this->level = $level;
    $this->status = $status;
    parent::__construct($message);
  }

  /**
   * Gets the level.
   *
   * @return int
   *   An integer status code. @see Migration::MESSAGE_*
   */
  public function getLevel() {
    return $this->level;
  }

  /**
   * Gets the status of the current item.
   *
   * @return int
   *   An integer status code. @see MigrateMap::STATUS_*
   */
  public function getStatus() {
    return $this->status;
  }

}
