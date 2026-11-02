#!/usr/bin/env php
<?php

/**
 * @file
 * A command line application to dump a database to a generation script.
 */

use Drupal\Core\Command\DbDumpCommand;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Symfony\Component\Console\Application;
use Symfony\Component\HttpFoundation\Request;

if (PHP_SAPI !== 'cli') {
  return;
}

require_once __DIR__ . '/../../autoload_runtime.php';

return static function () {
  // Bootstrap.
  $autoloader = require __DIR__ . '/../../autoload.php';
  $request = Request::createFromGlobals();
  Settings::initialize(dirname(__DIR__, 2), DrupalKernel::findSitePath($request), $autoloader);
  DrupalKernel::createFromRequest($request, $autoloader, 'prod')->boot();

  // Run the database dump command.
  (new Application())
    ->addCommand(new DbDumpCommand())
    ->getApplication()
    ->setDefaultCommand('dump-database-d8-mysql', TRUE)
    ->run();
};
