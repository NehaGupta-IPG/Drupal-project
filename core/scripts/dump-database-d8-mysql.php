#!/usr/bin/env php
<?php

/**
 * @file
 * A command line application to dump a database to a generation script.
 */

use Drupal\Core\Command\DbDumpApplication;
use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

if (PHP_SAPI !== 'cli') {
  return;
}

// Bootstrap.
$autoloader = require_once 'autoload.php';
$request = Request::createFromGlobals();
DrupalKernel::createFromRequest($request, $autoloader, 'prod')->boot();

// Run the database dump command.
$application = new DbDumpApplication();
$application->run();
