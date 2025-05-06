<?php

declare(strict_types=1);

namespace Drupal\KernelTests;

use Composer\Autoload\Classloader;
use Drupal\Core\Extension\ModuleExtensionList;

/**
 * Trait for testing the autoloader.
 *
 * @phpstan-require-extends \PHPUnit\Framework\Assert
 */
trait AutoloaderTrait {

  /**
   * Removes PSR-4 namespaces from class loader for uninstalled modules.
   *
   * TestRunnerKernel registers namespaces for all modules, including
   * uninstalled modules. This method removes the PSR-4 namespace for the list
   * of modules passed in after confirming they are all uninstalled.
   *
   * @param \Composer\Autoload\Classloader $classLoader
   *   The class loader.
   * @param string[] $modules
   *   List of machine names of modules that are uninstalled and whose PSR-4
   *   namespaces should be removed from the class loader.
   */
  protected static function disablePsr4ForUninstalledModules(Classloader $classLoader, array $modules): void {
    $availableModules = static::moduleExtensionList()->getAllAvailableInfo();
    $installedModules = static::moduleExtensionList()->getAllInstalledInfo();
    $prefixes = $classLoader->getPrefixesPsr4();
    foreach ($modules as $module) {
      static::assertArrayHasKey($module, $availableModules);
      static::assertArrayNotHasKey($module, $installedModules);
      $prefix = sprintf('Drupal\%s\\', $module);
      if (isset($prefixes[$prefix])) {
        // Cannot actually remove the PSR4 prefix from the class loader, so set
        // the path to a wrong location.
        $classLoader->setPsr4($prefix, '');
      }
    }
  }

  /**
   * The module extension list service.
   */
  private static function moduleExtensionList(): ModuleExtensionList {
    return \Drupal::service('extension.list.module');
  }

}
