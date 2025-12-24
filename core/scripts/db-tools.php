#!/usr/bin/env php
<?php

/**
 * @file
 * A command line application to import a database generation script.
 */

use Drupal\Core\Command\DbToolsApplication;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

if (PHP_SAPI !== 'cli') {
  return;
}

// Bootstrap.
// @todo Move from front-controller into AppContext on request.
$glue = require_once __DIR__ . '/../../autoload_runtime.php';
$request = Request::createFromGlobals();
Settings::initialize(dirname(__DIR__, 2), DrupalKernel::findSitePath($request), $glue->autoloader);
DrupalKernel::createFromRequest($request, $glue->autoloader, $glue->appContext)->boot();

$glue(new DbToolsApplication());
