<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

/**
 * Interface for a database connection parameters object.
 *
 * @phpstan-import-type ConnectionInfoBaseArray from \Drupal\Core\Database\Database
 * @phpstan-import-type UnprocessedConnectionInfoArray from \Drupal\Core\Database\Database
 *
 * @phpstan-type UrlComponents array{
 *   'scheme'?: string,
 *   'path'?: string,
 *   'user'?: string,
 *   'pass'?: string,
 *   'host'?: string,
 *   'port'?: string|int,
 *   'fragment'?: string,
 *   'query'?: string,
 * }
 */
interface ConnectionParametersInterface {

  // phpcs:disable
  /**
   * The driver name.
   */
  public string $driver { set; }

  /**
   * The module extension providing the driver.
   */
  public string $module { set; }

  /**
   * The driver PHP namespace.
   */
  public string $namespace { set; }

  /**
   * The driver PHP namespace autoload directory.
   */
  public string $autoload { set; }

  /**
   * The database name.
   */
  public string $database { set; }

  /**
   * The tables prefix.
   */
  public string $prefix { set; }
  // phpcs:enable

  /**
   * Returns the list of parameters.
   *
   * Only the parameters defined as properties are returned, those added to
   * ::$unknownOptions are ignored.
   *
   * @return list<string>
   *   The list of parameters' names.
   */
  public static function getNames(): array;

  /**
   * Returns the PHP class name of the connection.
   *
   * @return class-string<Connection>
   *   The PHP class name of the connection.
   */
  public function getConnectionClass(): string;

  /**
   * Returns the connection parameters as an associative array.
   *
   * @return ConnectionInfoBaseArray
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
   * @return ConnectionInfoBaseArray
   *   The processed options.
   *
   * @internal
   */
  public static function processOptionsArray(array $options): array;

  /**
   * Parses an array of URL parts into an array of connection options.
   *
   * This is used internally by the database layer, should not be called
   * otherwise.
   *
   * @param UrlComponents $urlComponents
   *   The URL components.
   * @param array<int|string, array<mixed>|string> $queryComponents
   *   The Query URL sub-components.
   *
   * @return UnprocessedConnectionInfoArray
   *   The connection options.
   *
   * @internal
   */
  public static function parseUrlPartsIntoOptions(array $urlComponents, array $queryComponents): array;

}
