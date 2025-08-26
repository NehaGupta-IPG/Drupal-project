<?php

namespace Drupal\system\Form;

use Drupal\Core\Config\PreExistingConfigException;
use Drupal\Core\Config\UnmetDependenciesException;
use Drupal\Core\Extension\ExtensionLifecycle;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides functionality used by module and theme forms.
 */
trait ExtensionFormTrait {
  use ModulesEnabledTrait;

  /**
   * Module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * Module Handler.
   *
   * @var Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Helper function for building a list of modules to install.
   *
   * @param string[] $enable
   *   The machine names of modules that should be enabled.
   *
   * @return array
   *   An array of modules to install and their dependencies.
   */
  protected function buildModuleList($enable) {
    // Build a list of modules to install.
    $modules = [
      'install' => [],
      'dependencies' => [],
      'experimental' => [],
    ];

    $data = $this->moduleExtensionList->getList();
    foreach ($data as $name => $module) {
      // If the module is installed there is nothing to do.
      if ($this->moduleHandler->moduleExists($name)) {
        continue;
      }
      // Required modules have to be installed.
      if (!empty($module->required)) {
        $modules['install'][$name] = $module->info['name'];
      }
      // Selected modules should be installed.
      elseif (in_array($name, $enable)) {
        $modules['install'][$name] = $data[$name]->info['name'];
        // Identify experimental modules.
        if ($data[$name]->info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER] === ExtensionLifecycle::EXPERIMENTAL) {
          $modules['experimental'][$name] = $data[$name]->info['name'];
        }
      }
    }

    // Add all dependencies to a list.
    foreach ($modules['install'] as $module => $value) {
      foreach (array_keys($data[$module]->requires) as $dependency) {
        if (!isset($modules['install'][$dependency]) && !$this->moduleHandler->moduleExists($dependency)) {
          $modules['dependencies'][$module][$dependency] = $data[$dependency]->info['name'];
          $modules['install'][$dependency] = $data[$dependency]->info['name'];

          // Identify experimental modules.
          if ($data[$dependency]->info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER] === ExtensionLifecycle::EXPERIMENTAL) {
            $modules['experimental'][$dependency] = $data[$dependency]->info['name'];
          }
        }
      }
    }

    // Make sure the install API is available.
    include_once DRUPAL_ROOT . '/core/includes/install.inc';

    // Invoke hook_requirements('install'). If failures are detected, make
    // sure the dependent modules aren't installed either.
    foreach (array_keys($modules['install']) as $module) {
      if (!drupal_check_module($module)) {
        unset($modules['install'][$module]);
        unset($modules['experimental'][$module]);
        foreach (array_keys($data[$module]->required_by) as $dependent) {
          unset($modules['install'][$dependent]);
          unset($modules['dependencies'][$dependent]);
        }
      }
    }

    return $modules;
  }

  /**
   * Installs modules.
   *
   * @param array $modules
   *   An array of modules to install.
   * @param bool $reset_messenger
   *   If true, the messenger service is null before adding completion messages.
   */
  protected function installModules(array $modules, $reset_messenger = FALSE): void {
    if (!empty($modules['install'])) {
      try {
        $this->moduleInstaller->install(array_keys($modules['install']));
        $this->messenger()
          ->addStatus($this->modulesEnabledConfirmationMessage($modules['install']));
      }
      catch (PreExistingConfigException $e) {
        $this->messenger()->addError($this->modulesFailToEnableMessage($modules, $e));
        return;
      }
      catch (UnmetDependenciesException $e) {
        $this->messenger()->addError(
          $e->getTranslatedMessage($this->getStringTranslation(), $modules['install'][$e->getExtension()])
        );
        return;
      }

      if ($reset_messenger) {
        // Unset the messenger to make sure that we'll get the service from the
        // new container.
        $this->messenger = NULL;
      }
      $module_names = array_values($modules['install']);
      $this->messenger()->addStatus($this->formatPlural(count($module_names), 'Module %name has been enabled.', '@count modules have been enabled: %names.', [
        '%name' => $module_names[0],
        '%names' => implode(', ', $module_names),
      ]));
    }
  }

}
