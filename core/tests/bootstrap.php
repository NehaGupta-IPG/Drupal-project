<?php

/**
 * @file
 * Autoloader for Drupal PHPUnit testing.
 *
 * @see phpunit.xml.dist
 */

declare(strict_types=1);

use Drupal\TestTools\ErrorHandler\BootstrapErrorHandler;
use Drupal\TestTools\ErrorHandler\DrupalDebugClassLoader;
use Drupal\TestTools\Extension\DeprecationBridge\DeprecationHandler;
use Drupal\TestTools\TestRunner\PopulateClassLoader;
use PHPUnit\Runner\ErrorHandler as PhpUnitErrorHandler;

// We define the COMPOSER_INSTALL constant, so that PHPUnit knows where to
// autoload from. This is needed for tests run in isolation mode, because
// phpunit.xml.dist is located in a non-default directory relative to the
// PHPUnit executable.
if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
  define('PHPUNIT_COMPOSER_INSTALL', __DIR__ . '/../../autoload.php');
}

/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require __DIR__ . '/../../autoload.php';
$loader->add('Drupal\\TestTools', __DIR__);

// Do class loader population.
PopulateClassLoader::execute($loader, dirname(__DIR__, 2));
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

// Bootstrap the DeprecationHandler extension and the DebugClassloader to report
// deprecations.
if ($deprecationBridgeConfiguration = DeprecationHandler::getConfiguration()) {
  DeprecationHandler::init($deprecationBridgeConfiguration['ignoreFile'] ?? NULL);

  // Need to have an early error handler to manage deprecations triggered by
  // DebugClassLoader, that occur before tests' setUp() methods are called.
  // We pass an instance of the PHPUnit error handler to redirect any error not
  // managed by our layer back to PHPUnit.
  set_error_handler(new BootstrapErrorHandler(PhpUnitErrorHandler::instance()));

  // Enable the DebugClassLoader to get deprecations for methods' signature
  // changes.
  DrupalDebugClassLoader::enable();
}
