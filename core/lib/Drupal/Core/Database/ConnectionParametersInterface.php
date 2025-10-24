<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Interface for a database connection parameters object.
 */
interface ConnectionParametersInterface {

  /**
   * Returns the parameters.
   *
   * For BC reasons, we may need to return the parameters as an array and
   * not as an object.
   *
   * @return static|array<string, mixed>
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
