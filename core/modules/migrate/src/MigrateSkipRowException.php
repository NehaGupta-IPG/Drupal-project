<?php

namespace Drupal\migrate;

/**
 * This exception is thrown when a row should be skipped.
 *
 * This exception should be used in Migrate process plugins. Throwing it in a
 * source plugin may cause unexpected results in the count of rows processed.
 * And throwing it in a destination plugin causes an error.
 */
class MigrateSkipRowException extends \Exception {

  /**
   * Constructs a MigrateSkipRowException object.
   *
   * @param string $message
   *   The message for the exception.
   * @param bool $saveToMap
   *   TRUE to record as STATUS_IGNORED in the map, FALSE to skip silently.
   */
  public function __construct($message = '', protected $saveToMap = TRUE) {
    parent::__construct($message);
  }

  /**
   * Whether the thrower wants to record this skip in the map table.
   *
   * @return bool
   *   TRUE to record as STATUS_IGNORED in the map, FALSE to skip silently.
   */
  public function getSaveToMap() {
    return $this->saveToMap;
  }

}
