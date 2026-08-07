<?php

/**
 * @file
 * Includes the autoload_runtime created by the Symfony Runtime component.
 *
 * @see composer.json
 * @see index.php
 * @see core/install.php
 * @see core/rebuild.php
 */

use Drupal\Core\Runtime\DrupalRuntime;

// Some environments do not provide SCRIPT_NAME. This can cause modules to break
// if they rely on Symfony/Component/HTTPFoundation/Request::getScriptName()
// Adapted from Symfony/Component/HTTPFoundation/Request::prepareBaseUrl()
if (empty($_SERVER['SCRIPT_NAME'])) {
  $filename = $_SERVER['SCRIPT_FILENAME'];
  $basename = basename($filename);
  if (basename($_SERVER['PHP_SELF'] ?? '') === $basename) {
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];
  }
  elseif (basename($_SERVER['ORIG_SCRIPT_NAME'] ?? '') === $basename) {
    $_SERVER['SCRIPT_NAME'] = $_SERVER['ORIG_SCRIPT_NAME'];
  }
  else {
    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', DIRECTORY_SEPARATOR);
    if (!empty($docroot) && str_starts_with($filename, $docroot)) {
      $_SERVER['SCRIPT_NAME'] = substr($filename, strlen($docroot));
    }
  }
}

// Get the path prefix from the environment variable DRUPAL_WEBROOT and then
// remove it from the beginning of SCRIPT_NAME, if present. The path prefix
// should contain directories between an .htaccess file redirecting requests
// into Drupal's /web directory, and the directory itself.
$webroot = getenv('DRUPAL_WEBROOT');
if ($webroot !== FALSE && str_starts_with($_SERVER['SCRIPT_NAME'], $webroot)) {
  $_SERVER['SCRIPT_NAME'] = substr($_SERVER['SCRIPT_NAME'], strlen($webroot));
}

// By default, the symfony/runtime component would load SymfonyRuntime as its
// runtime. However, Drupal's Kernel has a lot of runtime components that it
// expects to be prepared. Thus, we default Drupal applications to DrupalRuntime
// instead to make this easily accessible.
$_ENV['APP_RUNTIME'] ??= $_SERVER['APP_RUNTIME'] ?? DrupalRuntime::class;
return require __DIR__ . '/vendor/autoload_runtime.php';
