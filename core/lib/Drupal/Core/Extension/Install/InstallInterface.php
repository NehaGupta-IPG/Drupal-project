<?php

namespace Drupal\Core\Extension\Install;

/**
 * Method for installing a module or profile.
 *
 * Classes implementing this must be in the Install\Installer namespace.
 * For example src/Install/Installer/ModuleNameInstallation.php.
 * If you want to inject dependencies then also implement
 * ContainerInjectionInterface. Only core services can safely be injected
 * since this can run before Drupal is fully installed.
 */
interface InstallInterface {

  /**
   * Perform setup tasks when the module is installed.
   *
   * Only procedural implementations are supported for this hook.
   *
   * If the module implements hook_schema(), the database tables will
   * be created before this hook is fired.
   *
   * If the module provides a MODULE.routing.yml or alters routing information
   * these changes will not be available when this hook is fired. If up-to-date
   * router information is required, for example to use \Drupal\Core\Url, then
   * (preferably) use hook_modules_installed() or rebuild the router in the
   * hook_install() implementation.
   *
   * Implementations of this hook are by convention declared in the module's
   * .install file. The implementation can rely on the .module file being
   * loaded. The hook will only be called when a module is installed. The
   * module's schema version will be set to the module's greatest numbered
   * update hook. Because of this, any time a hook_update_N() is added to the
   * module, this function needs to be updated to reflect the current version
   *  of the database schema.
   *
   * See the @link https://www.drupal.org/node/146843 Schema API documentation
   * @endlink for details on hook_schema and how database tables are defined.
   *
   * Note that since this function is called from a full bootstrap, all
   * functions (including those in modules enabled by the current page request)
   * are available when this hook is called. Use cases could be displaying a
   * user message, or calling a module function necessary for initial setup,
   * etc.
   *
   * Ensure that anything added or modified in this function that can be
   * removed during uninstall should be removed with hook_uninstall().
   *
   * @param bool $isSyncing
   *   TRUE if the module is being installed as part of a configuration import.
   *   In these cases, your hook implementation needs to carefully consider
   *   what changes to configuration objects or configuration entities. Those
   *   changes should be made earlier and exported so during import there's no
   *   need to do them again.
   *
   * @see \Drupal\Core\Config\ConfigInstallerInterface::isSyncing
   * @see \Drupal\Core\Extension\Install\SchemaInterface::getSchema()
   * @see \Drupal\Core\Extension\ModuleInstaller::install()
   * @see \Drupal\Core\Extension\Install\UninstallInterface::doUninstall()
   * @see hook_modules_installed()
   */
  public function doInstall(bool $isSyncing): void;

}
