#!/usr/bin/env php
<?php

/**
 * @file
 * A command line application to dump a database to a generation script.
 */

use Drupal\Core\AppContextInterface;
use Drupal\Core\Command\DbDumpApplication;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

if (PHP_SAPI !== 'cli') {
  return;
}

require_once __DIR__ . '/../../autoload_runtime.php';

return static function (Request $request, AppContextInterface $app_context) {
  // Bootstrap.
  $autoloader = require __DIR__ . '/../../autoload.php';
  Settings::initialize($app_context->getAppRoot(), $app_context->getSitePath(), $autoloader);
  DrupalKernel::createFromRequest($request, $autoloader, 'prod', TRUE, $app_context)->boot();

  // Run the database dump command.
  return new DbDumpApplication();
};
