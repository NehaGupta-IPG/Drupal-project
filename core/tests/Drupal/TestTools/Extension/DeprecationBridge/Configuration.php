<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge;

/**
 * Configuration for DeprecationHandler.
 *
 * @internal
 */
final class Configuration {

  /**
   * The class singleton.
   *
   * phpcs:ignore Drupal.Commenting.VariableComment.MissingVar
   */
  private static self $instance;

  /**
   * Indicates if the project deprecation ignores are enabled.
   */
  private bool $projectIgnores = FALSE;

  /**
   * Path to the deprecation ignore file.
   */
  private string $ignoreFilePath;

  /**
   * Indicates if the debug class loader is enabled.
   */
  private bool $debugClassLoader = FALSE;

  /**
   * Indicates if the deprecation stats collection is enabled.
   */
  private bool $collectStats = FALSE;

  /**
   * A list of deprecation message patterns that should be ignored.
   *
   * @var list<string>
   */
  private array $deprecationIgnorePatterns = [];

  /**
   * Returns the singleton.
   *
   * @return self
   *   The singleton.
   */
  public static function instance(): self {
    if (!isset(self::$instance)) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Loads the configuration from an array.
   *
   * Overwrites each configuration key that is already existing.
   *
   * @param array<string, mixed> $parameters
   *   An array of configuration elements.
   */
  public function load(array $parameters): void {
    if (array_key_exists('enableProjectIgnores', $parameters)) {
      $this->projectIgnores = filter_var($parameters['enableProjectIgnores'] ?? FALSE, \FILTER_VALIDATE_BOOLEAN);
    }
    if (array_key_exists('enableDebugClassLoader', $parameters)) {
      $this->debugClassLoader = filter_var($parameters['enableDebugClassLoader'] ?? FALSE, \FILTER_VALIDATE_BOOLEAN);
    }
    if (array_key_exists('enableCollectStats', $parameters)) {
      $this->collectStats = filter_var($parameters['enableCollectStats'] ?? FALSE, \FILTER_VALIDATE_BOOLEAN);
    }
    if (array_key_exists('projectIgnoreFile', $parameters)) {
      assert(!empty($parameters['projectIgnoreFile']), 'projectIgnoreFile must be not empty');
      $this->ignoreFilePath = $parameters['projectIgnoreFile'];
    }
  }

  /**
   * Determines if the project ignores are enabled.
   *
   * @return bool
   *   TRUE if enabled, FALSE if disabled.
   */
  public static function projectIgnoresEnabled(): bool {
    return self::instance()->projectIgnores;
  }

  /**
   * Determines if the debug class loader is enabled.
   *
   * @return bool
   *   TRUE if enabled, FALSE if disabled.
   */
  public static function debugClassLoaderEnabled(): bool {
    return self::instance()->debugClassLoader;
  }

  /**
   * Determines if collection of deprecation statistics is enabled.
   *
   * @return bool
   *   TRUE if enabled, FALSE if disabled.
   */
  public static function collectStatsEnabled(): bool {
    return self::instance()->collectStats;
  }

  /**
   * Loads the deprecation ignore patterns from the ignore file.
   */
  public function loadDeprecationIgnorePatterns(): void {
    assert(empty($this->deprecationIgnorePatterns), 'deprecationIgnorePatterns can only be loaded once');

    // Load the deprecation ignore patterns from the specified file.
    if (!is_file($this->ignoreFilePath)) {
      throw new \InvalidArgumentException(sprintf('The ignoreFile "%s" does not exist.', $this->ignoreFilePath));
    }
    $path = $this->ignoreFilePath;
    set_error_handler(static function ($t, $m) use ($path, &$line): void {
      throw new \RuntimeException(sprintf('Invalid pattern found in "%s" on line "%d"', $path, 1 + $line) . substr($m, 12));
    });
    try {
      foreach (file($this->ignoreFilePath) as $line => $pattern) {
        if ((trim($pattern)[0] ?? '#') !== '#') {
          preg_match($pattern, '');
          $this->deprecationIgnorePatterns[] = $pattern;
        }
      }
    }
    finally {
      restore_error_handler();
    }
  }

  /**
   * Returns the list of deprecation message patterns that should be ignored.
   *
   * @return list<string>
   *   The list of deprecation message patterns.
   */
  public static function deprecationIgnorePatterns(): array {
    return self::instance()->deprecationIgnorePatterns;
  }

}
