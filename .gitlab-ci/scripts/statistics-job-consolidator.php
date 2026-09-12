#!/usr/bin/env php
<?php

/**
 * @file
 * Consolidate statistics within a CI job.
 */

use Drupal\Core\Database\Database;
use Drupal\TestTools\Extension\DeprecationBridge\CiJobStatisticsConsolidator;

function jobInfo(): array {
  $tmp = [];
  if ($env = getenv('CI_PIPELINE_ID')) {
    $tmp['CI_PIPELINE_ID'] = $env;
  }
  if ($env = getenv('CI_PIPELINE_NAME')) {
    $tmp['CI_PIPELINE_NAME'] = $env;
  }
  if ($env = getenv('CI_JOB_ID')) {
    $tmp['CI_JOB_ID'] = $env;
  }
  if ($env = getenv('CI_JOB_STAGE')) {
    $tmp['CI_JOB_STAGE'] = $env;
  }
  if ($env = getenv('CI_JOB_NAME')) {
    $tmp['CI_JOB_NAME'] = $env;
  }
  return $tmp;
}

function jobContext(): array {
  if ($env = getenv('DRUPAL_TEST_CONTEXT_KEYS')) {
    $contextKeys = explode(",", $env);
  }
  else {
    $contextKeys = [];
  }
  $tmp = [];
  $env = getenv('TESTSUITE');
  if ($env !== FALSE && in_array('phpunit-testsuite', $contextKeys)) {
    $tmp['phpunit_testsuite'] = $env;
  }
  if (in_array('php-version', $contextKeys)) {
    // PHP major/minor version.
    preg_match('/^\d+\.\d+/', \PHP_VERSION, $matches);
    $tmp['php_version'] = $matches[0];
  }
  if (in_array('database-driver', $contextKeys)) {
    // Database driver.
    if (!empty(getenv('SIMPLETEST_DB'))) {
      $currDir = getcwd();
      chdir(dirname(__DIR__, 6));
      $dbInfo = Database::convertDbUrlToConnectionInfo(getenv('SIMPLETEST_DB'), TRUE);
      chdir($currDir);
      $tmp['database_driver'] = $dbInfo['driver'];
    }
  }
  if (in_array('database-version', $contextKeys)) {
    // Database version.
    if (!empty(getenv('_TARGET_DB'))) {
      $tmp['database_version'] = getenv('_TARGET_DB');
    }
  }
  return $tmp;
}

if (PHP_SAPI !== 'cli') {
  return;
}

$args = $_SERVER['argv'];

if (!isset($args[1])) {
  echo "Artifacts path not specified.\n";
  exit(2);
}

if (!isset($args[2])) {
  echo "Consolidation file not specified.\n";
  exit(2);
}

/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require __DIR__ . '/../../autoload.php';
$loader->addPsr4('Drupal\\TestTools\\', __DIR__ . '/../../core/tests/Drupal/TestTools');

(new CiJobStatisticsConsolidator(jobInfo(), jobContext()))->process($args[1], $args[2]);

exit(0);
