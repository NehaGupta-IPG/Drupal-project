<?php

namespace Drupal\Core\Database;

/**
 * Interface for a database exception.
 *
 * Database drivers should catch lower-level database client exceptions and
 * throw exceptions that implement this interface to allow database
 * abstraction in Drupal.
 */
// phpcs:ignore Drupal.Classes.InterfaceName.InterfaceSuffix
interface DatabaseException extends \Throwable {

}
