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

use Drupal\Core\AppContextInterface;
use Drupal\Core\Command\GenerateProxyClassApplication;
use Drupal\Core\DrupalKernel;
use Drupal\Core\ProxyBuilder\ProxyBuilder;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

if (PHP_SAPI !== 'cli') {
  return;
}

require_once __DIR__ . '/../../autoload_runtime.php';

return static function (Request $request, AppContextInterface $app_context) {
  // Bootstrap.
  // @todo Move from front-controller into runtime on request.
  $autoloader = require __DIR__ . '/../../autoload.php';
  Settings::initialize($app_context->getAppRoot(), $app_context->getSitePath(), $autoloader);
  DrupalKernel::createFromRequest($request, $autoloader, 'prod', TRUE, $app_context)->boot();

  // Run the database dump command.
  return new GenerateProxyClassApplication(new ProxyBuilder());
};
