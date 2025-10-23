<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Interface for a database connection parameters object.
 */
interface ConnectionParametersInterface {

  /**
   * Returns a new instance of connection parameters.
   *
   * @param array<string, mixed> $options
   *   An array of connection options.
   *
   * @return static
   *   A new instance of connection parameters.
   */
  public static function createFromOptionsArray(array $options): static;

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
   * @return array<string, mixed>
   *   The connection parameters as an associative array.
   */
  public function toArray(): array;

  /**
   * Processes an array of connection options for driver specific defaults.
   *
   * This is used internally by the database layer, should not be called
   * otherwise.
   *
   * @param array<string, mixed> $options
   *   An array of connection options.
   *
   * @return array<string, mixed>
   *   The processed options as an associative array.
   *
   * @internal
   */
  public static function processOptionsArray(array $options): array;

}
