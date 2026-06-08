#!/usr/bin/env php
<?php

/**
 * @file
 * A command line application to dump a database to a generation script.
 */

use Composer\Autoload\ClassLoader;
use Drupal\Core\Command\DbDumpApplication;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

if (PHP_SAPI !== 'cli') {
  return;
}

require_once __DIR__ . '/../../autoload_runtime.php';

return static function (string $environment, ClassLoader $autoloader, Request $request) {
  // Bootstrap.
  Settings::initialize(dirname(__DIR__, 2), DrupalKernel::findSitePath($request), $autoloader);
  DrupalKernel::createFromRequest($request, $autoloader, $environment)->boot();

  // Run the database dump command.
  return new DbDumpApplication();
};
