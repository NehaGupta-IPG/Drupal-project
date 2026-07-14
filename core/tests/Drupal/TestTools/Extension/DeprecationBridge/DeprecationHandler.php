<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge;

use Drupal\TestTools\ErrorHandler\DrupalDebugClassLoader;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration as PhpUnitConfiguration;

// cspell:ignore bootstrapper bootstrappers

/**
 * Drupal's PHPUnit extension to manage code deprecation.
 *
 * In the future this extension might be dropped if PHPUnit adds support for
 * ignoring a specified list of deprecations.
 *
 * @internal
 */
final class DeprecationHandler implements Extension {

  /**
   * Load configuration and setup.
   *
   * This needs to execute before PHPUnit's extension bootstrap phase, since
   * there is the need to set early both the error handler and the debug class
   * loader. This static method is called by the bootstrap.php code, that is
   * executed first before the extensions bootstrap.
   *
   * @param \PHPUnit\TextUI\Configuration\Configuration $configuration
   *   The PHPUnit configuration.
   */
  public static function preBootstrap(PhpUnitConfiguration $configuration): void {
    $config = Configuration::instance();

    // Find the extension parameters from current PHPUnit configuration.
    $extensionBootstrappers = $configuration->extensionBootstrappers();
    $parameters = [];
    foreach ($extensionBootstrappers as $bootstrapper) {
      if ($bootstrapper['className'] === self::class) {
        $parameters = $bootstrapper['parameters'];
        break;
      }
    }
    $config->load($parameters);

    // Get the overridden configuration provided via env variable.
    $environmentVariable = getenv('DRUPAL_DEPRECATION_FILTER_CONFIG');
    if ($environmentVariable !== FALSE) {
      $overrideConfig = (array) json_decode($environmentVariable);
      $config->load($overrideConfig);
    }

    // Determine if project ignores are enabled. If so, loads the deprecation
    // patterns to be ignored from the ignore file to allows capturing
    // deprecations triggered by PHP or by the DebugClassLoader, that can occur
    // before tests' ::setUp() methods are called.
    if ($config->projectIgnoresEnabled) {
      $config->loadDeprecationIgnorePatterns();
    }

    // Enable the DebugClassLoader to get deprecations for methods' signature
    // changes.
    if ($config->debugClassLoaderEnabled) {
      DrupalDebugClassLoader::enable();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function bootstrap(
    PhpUnitConfiguration $configuration,
    Facade $facade,
    ParameterCollection $parameters,
  ): void {
  }

}
