#!/usr/bin/env php
<?php

/**
 * @file
 * A script to generate proxy classes for lazy services.
 *
 * For help, type this command from the root directory of an installed Drupal
 * site: php core/scripts/generate-proxy-class.php -h generate-proxy-class
 *
 * @ingroup container
 *
 * @see lazy_services
 */

use Composer\Autoload\ClassLoader;
use Drupal\Core\Command\GenerateProxyClassApplication;
use Drupal\Core\DrupalKernel;
use Drupal\Core\ProxyBuilder\ProxyBuilder;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

if (PHP_SAPI !== 'cli') {
  return;
}

require_once __DIR__ . '/../../autoload_runtime.php';

return static function (string $environment, ClassLoader $autoloader) {
  // Bootstrap.
  $request = Request::createFromGlobals();
  Settings::initialize(dirname(__DIR__, 2), DrupalKernel::findSitePath($request), $autoloader);
  DrupalKernel::createFromRequest($request, $autoloader, $environment)->boot();

  // Run the database dump command.
  return new GenerateProxyClassApplication(new ProxyBuilder());
};
