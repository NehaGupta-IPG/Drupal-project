<?php

declare(strict_types=1);

namespace Drupal\TestTools\TestRunner;

use Composer\Autoload\ClassLoader;

/**
 * Add test classes to autoloading.
 *
 * @internal
 */
class PopulateClassLoader {

  /**
   * Populate class loader with additional namespaces for tests.
   *
   * We run this in a function to avoid setting the class loader to a global
   * that can change. This change can cause unpredictable false positives for the
   * PHPUnit global state change watcher. The class loader can be retrieved from
   * composer at any time by requiring autoload.php.
   */
  public static function execute(ClassLoader $loader, string $root): void {
    $testDir = $root . \DIRECTORY_SEPARATOR . 'core/tests';

    // Start with classes in known locations.
    $loader->add('Drupal\\BuildTests', $testDir);
    $loader->add('Drupal\\Tests', $testDir);
    $loader->add('Drupal\\TestSite', $testDir);
    $loader->add('Drupal\\KernelTests', $testDir);
    $loader->add('Drupal\\FunctionalTests', $testDir);
    $loader->add('Drupal\\FunctionalJavascriptTests', $testDir);

    if (!isset($GLOBALS['namespaces'])) {
      // Scan for arbitrary extension namespaces from core and contrib.
      $extension_roots = self::getContribExtensionDirectoryRoots($root);

      $dirs = array_map([self::class, 'findExtensionDirectories'], $extension_roots);
      $dirs = array_reduce($dirs, 'array_merge', []);
      $GLOBALS['namespaces'] = self::getExtensionNamespaces($dirs);
    }
    foreach ($GLOBALS['namespaces'] as $prefix => $paths) {
      $loader->addPsr4($prefix, $paths);
    }
  }

  /**
   * Finds all valid extension directories recursively within a given directory.
   *
   * @param string $scan_directory
   *   The directory that should be recursively scanned.
   *
   * @return array
   *   An associative array of extension directories found within the scanned
   *   directory, keyed by extension name.
   */
  private static function findExtensionDirectories(string $scan_directory): array {
    $extensions = [];
    $dirs = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($scan_directory, \RecursiveDirectoryIterator::FOLLOW_SYMLINKS));
    foreach ($dirs as $dir) {
      if (str_contains($dir->getPathname(), '.info.yml')) {
        // Cut off ".info.yml" from the filename for use as the extension name. We
        // use getRealPath() so that we can scan extensions represented by
        // directory aliases.
        $extensions[substr($dir->getFilename(), 0, -9)] = $dir->getPathInfo()
          ->getRealPath();
      }
    }
    return $extensions;
  }

  /**
   * Returns directories under which contributed extensions may exist.
   *
   * @param string|null $root
   *   (optional) Path to the root of the Drupal installation.
   *
   * @return array
   *   An array of directories under which contributed extensions may exist.
   */
  private static function getContribExtensionDirectoryRoots(string $root): array {
    $paths = [
      $root . '/core/modules',
      $root . '/core/profiles',
      $root . '/core/themes',
      $root . '/modules',
      $root . '/profiles',
      $root . '/themes',
    ];
    $sites_path = $root . '/sites';
    // Note this also checks sites/../modules and sites/../profiles.
    foreach (scandir($sites_path) as $site) {
      if ($site[0] === '.' || $site === 'simpletest') {
        continue;
      }
      $path = "$sites_path/$site";
      $paths[] = is_dir("$path/modules") ? realpath("$path/modules") : NULL;
      $paths[] = is_dir("$path/profiles") ? realpath("$path/profiles") : NULL;
      $paths[] = is_dir("$path/themes") ? realpath("$path/themes") : NULL;
    }
    return array_filter($paths);
  }

  /**
   * Registers the namespace for each extension directory with the autoloader.
   *
   * @param array $dirs
   *   An associative array of extension directories, keyed by extension name.
   *
   * @return array
   *   An associative array of extension directories, keyed by their namespace.
   */
  private static function getExtensionNamespaces(array $dirs): array {
    $namespaces = [];
    foreach ($dirs as $extension => $dir) {
      if (is_dir($dir . '/src')) {
        // Register the PSR-4 directory for module-provided classes.
        $namespaces['Drupal\\' . $extension . '\\'][] = $dir . '/src';
      }
      if (is_dir($dir . '/tests/src')) {
        // Register the PSR-4 directory for PHPUnit-based suites.
        $namespaces['Drupal\\Tests\\' . $extension . '\\'][] = $dir . '/tests/src';
      }
    }
    return $namespaces;
  }

}
