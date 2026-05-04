<?php

/**
 * @file
 * Includes the autoloader created by Composer.
 *
 * This file is to be included by scripts in this folder. It will not work for
 * files in a different location.
 */

// Get the app root so we can include the autoload.php file there. This will
// either be the drupal/drupal package's autoload.php, or the scaffolded
// autoload.php, depending on how Drupal has been installed. In either case, the
// file then includes the Composer autoloader.
// To get the app root from here, we need to use 'exec('pwd')' rather than
// 'getcwd', to get the directory the script is being run from without any
// symlinks being resolved. We remove an initial './' from the name of the
// script being run, but not an initial '../' as that gets us to the right
// place if this is being run from a deeper directory.
$app_root = dirname(exec('pwd') . '/' . preg_replace('@^./@', '', $_SERVER['argv'][0]), 3);
if (!file_exists($app_root . '/autoload.php')) {
  exit("Unable to find project root autoload.php file in app root '$app_root'.\n");
}
return require_once $app_root . '/vendor/autoload.php';
