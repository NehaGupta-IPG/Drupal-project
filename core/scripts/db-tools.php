#!/usr/bin/env php
<?php

/**
 * @file
 * A command line application to import a database generation script.
 */

use Drupal\Core\Command\DbToolsApplication;
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
$application = new DbToolsApplication();
$application->run();
