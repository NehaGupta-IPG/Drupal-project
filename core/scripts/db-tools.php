#!/usr/bin/env php
<?php

/**
 * @file
 * A command line application to import a database generation script.
 */

use Drupal\Core\Command\DbDumpCommand;
use Drupal\Core\Command\DbImportCommand;
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
  // @todo Move from front-controller into runtime on request.
  $autoloader = require __DIR__ . '/../../autoload.php';
  $request = Request::createFromGlobals();
  Settings::initialize(dirname(__DIR__, 2), DrupalKernel::findSitePath($request), $autoloader);
  DrupalKernel::createFromRequest($request, $autoloader, 'prod')->boot();

  // Run the database dump command.
  $application = new Application('Database Tools', '8.0.x');
  $application->addCommands([new DbDumpCommand(), new DbImportCommand()]);
  $application->run();
};
