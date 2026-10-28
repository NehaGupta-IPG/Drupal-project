<?php

namespace Drupal\Core\Extension\Install;

/**
 * Method for uninstalling a module or profile.
 *
 * Classes implementing this must be in the Install\Uninstaller namespace.
 * For example src/Install/Uninstaller/ModuleNameUninstaller.php.
 * If you want to inject dependencies then also implement
 * ContainerInjectionInterface. Only core services can safely be injected.
 */
interface UninstallInterface {

  /**
   * Remove any information that the module sets.
   *
   * Only procedural implementations are supported for this hook.
   *
   * The information that the module should remove includes:
   * - state that the module has set using \Drupal::state()
   * - modifications to existing tables
   *
   * The module should not remove its entry from the module configuration.
   * Database tables defined by hook_schema() will be removed automatically.
   *
   * The uninstall hook must be implemented in the module's .install file. It
   * will fire when the module gets uninstalled but before the module's database
   * tables are removed, allowing your module to query its own tables during
   * this routine.
   *
   * Adding custom logic to hook_uninstall implementations to check for
   * criteria before uninstalling, does not take advantage of the module
   * uninstall page UI. Instead, use
   * \Drupal\Core\Extension\ModuleUninstallValidatorInterface.
   *
   * @param bool $isSyncing
   *   TRUE if the module is being uninstalled as part of a configuration
   *   import. In these cases, your hook implementation needs to carefully
   *   consider what changes to configuration objects or configuration
   *   entities. Those changes should be made earlier and exported so during
   *   import there's no need to do them again.
   *
   * @see \Drupal\Core\Extension\Install\InstallInterface::doInstall()
   * @see \Drupal\Core\Extension\Install\SchemaInterface::getSchema()
   * @see hook_modules_uninstalled()
   * @see \Drupal\Core\Extension\ModuleUninstallValidatorInterface
   */
  public function doUninstall(bool $isSyncing): void;

}
