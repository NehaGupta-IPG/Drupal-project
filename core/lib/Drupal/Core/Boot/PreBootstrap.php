<?php

namespace Drupal\Core\Boot;

use Composer\Autoload\ClassLoader;
use Drupal\Component\DependencyInjection\PhpArrayContainer;
use Drupal\PreBootstrapDefinition;

/**
 * Holds the pre-bootstrap container.
 */
class PreBootstrap {

  /**
   * The classloader. Optional.
   *
   * @var \Composer\Autoload\ClassLoader|null
   */
  protected static ?ClassLoader $autoloader;

  /**
   * The pre-bootstrap container.
   *
   * @var \Drupal\Component\DependencyInjection\PhpArrayContainer
   *   The pre-bootstrap container.
   */
  public static PhpArrayContainer $container;

  private function __construct() {
  }

  /**
   * Init.
   *
   * @param \Composer\Autoload\ClassLoader $autoloader
   *   The classloader.
   *
   * @return \Composer\Autoload\ClassLoader
   *   The classloader.
   */
  public static function init(ClassLoader $autoloader): ClassLoader {
    if (!isset(static::$autoloader)) {
      static::$autoloader = $autoloader;
      static::$container = new PhpArrayContainer(PreBootstrapDefinition::DEFINITION);
    }
    return static::$autoloader;
  }

}
