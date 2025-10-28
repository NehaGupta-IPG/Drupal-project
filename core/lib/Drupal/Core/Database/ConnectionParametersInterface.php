<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Interface for a database connection parameters object.
 *
 * @phpstan-import-type ConnectionInfoArray from \Drupal\Core\Database\Database
 */
interface ConnectionParametersInterface {

  /**
   * Returns the parameters.
   *
   * For BC reasons, we may need to return the parameters as an array and
   * not as an object.
   *
   * @return static|ConnectionInfoArray
   *   The connection parameters.
   */
  public function getParameters(): static|array;

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
   * @param ConnectionInfoArray $options
   *   An array of connection options.
   *
   * @return ConnectionInfoArray
   *   The processed options.
   *
   * @internal
   */
  public static function processOptionsArray(array $options): array;

}
