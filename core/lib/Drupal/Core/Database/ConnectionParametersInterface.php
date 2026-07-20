<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Interface for a database connection parameters object.
 *
 * @phpstan-import-type ConnectionInfoArray from \Drupal\Core\Database\Database
 * @phpstan-import-type UnprocessedConnectionInfoArray from \Drupal\Core\Database\Database
 */
interface ConnectionParametersInterface {

  /**
   * Returns the list of parameters.
   *
   * Only the parameters defined as properties are returned, those added to
   * static::$optionsBackfill are ignored.
   *
   * @return list<string>
   *   The list of parameters' names.
   */
  public static function getNames(): array;

  /**
   * Returns the PHP class name of the connection.
   *
   * @return class-string
   *   The PHP class name of the connection.
   */
  public function getConnectionClass(): string;

  /**
   * Returns the connection parameters as an associative array.
   *
   * @return ConnectionInfoArray
   *   The connection parameters as an associative array.
   */
  public function toArray(): array;

  /**
   * Returns the connection parameters as a URL.
   *
   * @return string
   *   The connection parameters as as a URL.
   */
  public function asUrl(): string;

  /**
   * Processes an array of connection options for driver specific defaults.
   *
   * This is used internally by the database layer, should not be called
   * otherwise.
   *
   * @param UnprocessedConnectionInfoArray $options
   *   An array of connection options.
   *
   * @return ConnectionInfoArray
   *   The processed options.
   *
   * @internal
   */
  public static function processOptionsArray(array $options): array;

}
