<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\Artifact;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use PHPUnit\Util\Filesystem;

/**
 * Drupal's extension for managing test artifacts.
 *
 * @internal
 */
final class ArtifactHandler implements Extension {

  /**
   * The path to the artifacts base directory.
   */
  private static string $directory;

  /**
   * The PHP major/minor version in use.
   */
  private static string $phpVersion;

  /**
   * The database driver used by the SUT.
   */
  private static ?string $databaseDriver = NULL;

  /**
   * The database version used by the SUT.
   */
  private static ?string $databaseVersion = NULL;

  /**
   * {@inheritdoc}
   */
  public function bootstrap(
    Configuration $configuration,
    Facade $facade,
    ParameterCollection $parameters,
  ): void {
    // Artifacts directory.
    // $directory = $parameters->get('directory');
    $directory = 'test-artifacts/jobs/' . getenv('CI_JOB_ID');
    Filesystem::createDirectory($directory);
    $realDirectory = realpath($directory);
    if ($realDirectory === FALSE || !is_dir($realDirectory) || !is_writable($realDirectory)) {
      throw new \RuntimeException("Test artifacts directory {$directory} is not a writable directory.");
    }
    self::$directory = $realDirectory;

    // PHP major/minor version.
    preg_match('/^\d+\.\d+/', \PHP_VERSION, $matches);
    self::$phpVersion = $matches[0];

    // Database version.
    if (!empty(getenv('_TARGET_DB'))) {
      self::$databaseVersion = getenv('_TARGET_DB');
    }

    // Set an environment variable with the configuration, so that isolated
    // tests can access it.
    $config = json_encode([
      'directory' => self::$directory,
      'php_version' => self::$phpVersion,
      'database_driver' => self::$databaseDriver,
      'database_version' => self::$databaseVersion,
    ]);
    putenv('_DRUPAL_TEST_ARTIFACT_CONFIG=' . $config);
  }

  public static function directory(): ?string {
    return self::$directory ?? NULL;
  }

  public static function phpVersion(): ?string {
    return self::$phpVersion ?? NULL;
  }

  public static function databaseDriver(): ?string {
    return self::$databaseDriver;
  }

  public static function databaseVersion(): ?string {
    return self::$databaseVersion;
  }

  public static function metadata(): array {
    $tmp = [];
    if ($env = getenv('CI_PIPELINE_ID')) {
      $tmp['CI_PIPELINE_ID'] = $env;
    }
    if ($env = getenv('CI_PIPELINE_NAME')) {
      $tmp['CI_PIPELINE_NAME'] = $env;
    }
    if ($env = getenv('CI_JOB_ID')) {
      $tmp['CI_JOB_ID'] = $env;
    }
    if ($env = getenv('CI_JOB_STAGE')) {
      $tmp['CI_JOB_STAGE'] = $env;
    }
    if ($env = getenv('CI_JOB_NAME')) {
      $tmp['CI_JOB_NAME'] = $env;
    }
    $tmp['php_version'] = self::phpVersion();
    if (self::databaseDriver()) {
      $tmp['database_driver'] = self::databaseDriver();
    }
    if (self::databaseVersion()) {
      $tmp['database_version'] = self::databaseVersion();
    }
    return $tmp;
  }

}
