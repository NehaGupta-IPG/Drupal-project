<?php

/**
 * @file
 * Drupal - symfony/runtime glue.
 * phpcs:disable
 */

use Composer\Autoload\ClassLoader;
use Drupal\Core\AppContext;
use Symfony\Component\Runtime\RuntimeInterface;

return new readonly class() {

  private RuntimeInterface $runtime;

  public AppContext $appContext;

  public ClassLoader $autoloader;

  public function __construct() {
    $this->autoloader = require __DIR__ . '/autoload.php';
    // Backwards compatibility.
    $GLOBALS['autoloader'] = $this->autoloader;
    $runtimeClass = $_ENV['APP_RUNTIME'] = $_SERVER['APP_RUNTIME'] ?? $_ENV['APP_RUNTIME'] ?? 'Drupal\\Core\\Runtime\\DrupalRuntime';
    $runtimeOptions = ($_SERVER['APP_RUNTIME_OPTIONS'] ?? $_ENV['APP_RUNTIME_OPTIONS'] ?? []) + array (
);
    $this->runtime = new $runtimeClass($runtimeOptions);
    $this->appContext = new AppContext($runtimeOptions);
  }

  public function __invoke($app): void {
    exit($this->runtime->getRunner($app)->run());
  }

};
