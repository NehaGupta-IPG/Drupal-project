<?php

namespace Drupal\Composer\Plugin\Scaffold;

use Composer\Composer;
use Composer\IO\IOInterface;
use Drupal\Composer\Plugin\Scaffold\Operations\ScaffoldResult;
use Drupal\Core\Runtime\DrupalRuntime;

/**
 * Generates an 'autoload_runtime.php'.
 *
 * @internal
 */
final class GenerateAutoloadRuntimeReferenceFile {

  /**
   * This class provides only static methods.
   */
  private function __construct() {
  }

  /**
   * Generates the autoload_runtime file at the specified location.
   *
   * @param \Composer\Composer $composer
   *   Composer.
   * @param string $web_root
   *   The path to the web root.
   *
   * @return \Drupal\Composer\Plugin\Scaffold\Operations\ScaffoldResult
   *   The result of the autoload_runtime file generation.
   */
  public static function generateAutoloadRuntime(Composer $composer, string $web_root): ScaffoldResult {
    $root_package = $composer->getPackage();
    $autoload_runtime_path = static::autoloadRuntimePath($root_package->getName(), $web_root);
    $extra = $root_package->getExtra();
    $runtime_class = $extra['runtime']['runtime_class'] ?? DrupalRuntime::class;
    $runtime_options = $extra['runtime']['runtime_options'] ?? [];
    $template = file_get_contents(__DIR__ . '/drupal_runtime.template');
    $runtime = strtr($template, [
      '%runtime_class%' => var_export($runtime_class, TRUE),
      '%runtime_options%' => var_export($runtime_options, TRUE),
    ]);
    file_put_contents($autoload_runtime_path->fullPath(), $runtime);
    return new ScaffoldResult($autoload_runtime_path, TRUE);
  }

  /**
   * Determines whether the autoload_runtime file has been committed into git.
   *
   * @param \Composer\IO\IOInterface $io
   *   IOInterface to write to.
   * @param string $package_name
   *   The name of the package defining the autoload_runtime file
   *   (the root package).
   * @param string $web_root
   *   The path to the web root.
   *
   * @return bool
   *   True if autoload_runtime.php file exists and has been committed to the
   *   repository
   */
  public static function autoloadRuntimeFileCommitted(IOInterface $io, string $package_name, string $web_root): bool {
    $autoload_runtime_path = static::autoloadRuntimePath($package_name, $web_root);
    $autoload_runtime_file = $autoload_runtime_path->fullPath();
    $location = dirname($autoload_runtime_file);
    if (!file_exists($autoload_runtime_file)) {
      return FALSE;
    }
    return Git::checkTracked($io, $autoload_runtime_file, $location);
  }

  /**
   * Generates a scaffold file path object for the autoload_runtime file.
   *
   * @param string $package_name
   *   The name of the package defining the autoload_runtime file
   *   (the root package).
   * @param string $web_root
   *   The path to the web root.
   *
   * @return \Drupal\Composer\Plugin\Scaffold\ScaffoldFilePath
   *   Object wrapping the relative and absolute path to the destination file.
   */
  protected static function autoloadRuntimePath(string $package_name, string $web_root): ScaffoldFilePath {
    $rel_path = 'autoload_runtime.php';
    $dest_rel_path = '[web-root]/' . $rel_path;
    $dest_full_path = $web_root . '/' . $rel_path;
    return new ScaffoldFilePath('autoload_runtime', $package_name, $dest_rel_path, $dest_full_path);
  }

}
