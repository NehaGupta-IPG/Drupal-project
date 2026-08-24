<?php

/**
 * @file
 * Autoloader for Drupal PHPUnit testing.
 *
 * @see phpunit.xml.dist
 */

declare(strict_types=1);

use Drupal\Core\Extension\Discovery\RecursiveExtensionFilterCallback;
use Drupal\TestTools\Extension\DeprecationBridge\DeprecationHandler;
use PHPUnit\TextUI\Configuration\Registry as PhpunitConfigurationRegistry;

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
function drupal_phpunit_find_extension_directories($scan_directory): array {
  $extensions = [];
  // Use Unix paths regardless of platform, skip dot directories, follow
  // symlinks (to allow extensions to be linked from elsewhere), and return
  // the RecursiveDirectoryIterator instance to have access to getSubPath(),
  // since SplFileInfo does not support relative paths.
  $flags = \FilesystemIterator::UNIX_PATHS;
  $flags |= \FilesystemIterator::SKIP_DOTS;
  $flags |= \FilesystemIterator::FOLLOW_SYMLINKS;
  $flags |= \FilesystemIterator::CURRENT_AS_SELF;
  $directory_iterator = new \RecursiveDirectoryIterator($scan_directory, $flags);
  $callback = new RecursiveExtensionFilterCallback(['node_modules', 'bower_components'], TRUE);
  $filter = new \RecursiveCallbackFilterIterator($directory_iterator, [$callback, 'accept']);
  $iterator = new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::LEAVES_ONLY, \RecursiveIteratorIterator::CATCH_GET_CHILD);
  foreach ($iterator as $file) {
    // Cut off ".info.yml" from the filename for use as the extension name. We
    // use getRealPath() so that we can scan extensions represented by
    // directory aliases.
    $extensions[substr($file->getFilename(), 0, -9)] = dirname($file->getRealPath() ?: $file->getPathname());
  }
  return $extensions;
}

/**
 * Returns directories from which test extensions may be discovered.
 *
 * @param string $root
 *   (optional) Path to the root of the Drupal installation.
 *
 * @return array
 *   A list of directories from which test extensions may be discovered.
 */
function drupal_phpunit_contrib_extension_directory_roots($root = NULL): array {
  if ($root === NULL) {
    $root = dirname(__DIR__, 2);
  }
  $paths = [
    $root . '/core',
    $root,
  ];
  $sites_path = $root . '/sites';
  foreach (scandir($sites_path) as $site) {
    if ($site[0] === '.' || $site === 'simpletest' || !is_dir("$sites_path/$site")) {
      continue;
    }
    $paths[] = "$sites_path/$site";
  }
  return $paths;
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
function drupal_phpunit_get_extension_namespaces($dirs): array {
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

// We define the COMPOSER_INSTALL constant, so that PHPUnit knows where to
// autoload from. This is needed for tests run in isolation mode, because
// phpunit.xml.dist is located in a non-default directory relative to the
// PHPUnit executable.
if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
  define('PHPUNIT_COMPOSER_INSTALL', __DIR__ . '/../../autoload.php');
}

/**
 * Populate class loader with additional namespaces for tests.
 *
 * We run this in a function to avoid setting the class loader to a global
 * that can change. This change can cause unpredictable false positives for the
 * PHPUnit global state change watcher. The class loader can be retrieved from
 * composer at any time by requiring autoload.php.
 */
function drupal_phpunit_populate_class_loader() {

  /** @var \Composer\Autoload\ClassLoader $loader */
  $loader = require __DIR__ . '/../../autoload.php';

  // Start with classes in known locations.
  $loader->add('Drupal\\BuildTests', __DIR__);
  $loader->add('Drupal\\Tests', __DIR__);
  $loader->add('Drupal\\TestSite', __DIR__);
  $loader->add('Drupal\\KernelTests', __DIR__);
  $loader->add('Drupal\\FunctionalTests', __DIR__);
  $loader->add('Drupal\\FunctionalJavascriptTests', __DIR__);
  $loader->add('Drupal\\TestTools', __DIR__);

  if (!isset($GLOBALS['namespaces'])) {
    // Scan for arbitrary extension namespaces from core and contrib.
    $extension_roots = drupal_phpunit_contrib_extension_directory_roots();

    $dirs = array_map('drupal_phpunit_find_extension_directories', $extension_roots);
    $dirs = array_reduce($dirs, 'array_merge', []);
    $GLOBALS['namespaces'] = drupal_phpunit_get_extension_namespaces($dirs);
  }
  foreach ($GLOBALS['namespaces'] as $prefix => $paths) {
    $loader->addPsr4($prefix, $paths);
  }

  return $loader;
}

// Do class loader population.
drupal_phpunit_populate_class_loader();
class_alias('\Drupal\Tests\DocumentElement', '\Behat\Mink\Element\DocumentElement', TRUE);

// Set sane locale settings, to ensure consistent string, dates, times and
// numbers handling.
// @see \Drupal\Core\DrupalKernel::bootEnvironment()
setlocale(LC_ALL, 'C.UTF-8', 'C');

// Set appropriate configuration for multi-byte strings.
mb_internal_encoding('utf-8');
mb_language('uni');

// Set the default timezone. While this doesn't cause any tests to fail, PHP
// complains if 'date.timezone' is not set in php.ini. The Australia/Sydney
// timezone is chosen so all tests are run using an edge case scenario (UTC+10
// and DST). This choice is made to prevent timezone related regressions and
// reduce the fragility of the testing system in general.
date_default_timezone_set('Australia/Sydney');

// Get deprecation override handling as early as possible, depending on the
// extension configuration.
try {
  DeprecationHandler::preBootstrap(PhpunitConfigurationRegistry::get());
}
catch (\AssertionError) {
  // In case this script is executed outside of a PHPUnit test run, the
  // configuration is not defined, so we cannot bootstrap the deprecation
  // handler extension. In such case, do nothing.
  // @see core/scripts/test-site.php for an example.
}
