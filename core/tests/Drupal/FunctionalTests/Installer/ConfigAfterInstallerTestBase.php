<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\KernelTests\AssertConfigTrait;

/**
 * Provides a class for install profiles to check their installed config.
 */
abstract class ConfigAfterInstallerTestBase extends InstallerTestBase {

  use AssertConfigTrait;

  /**
   * Ensures that all the installed config looks like the exported one.
   *
   * @param array $skipped_config
   *   An array of skipped config.
   */
  protected function assertInstalledConfig(array $skipped_config) {
    $this->addToAssertionCount(1);
    /** @var \Drupal\Core\Config\StorageInterface $active_config_storage */
    $active_config_storage = $this->container->get('config.storage');
    /** @var \Drupal\Core\Config\ConfigManagerInterface $config_manager */
    $config_manager = $this->container->get('config.manager');

    $default_install_path = 'core/profiles/' . $this->profile . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;
    $profile_config_storage = new FileStorage($default_install_path, StorageInterface::DEFAULT_COLLECTION);

    /** @var \Drupal\Core\Extension\ModuleExtensionList $module_list */
    $module_list = \Drupal::service('extension.list.module');
    $database_override_path = $module_list->getPath(\Drupal::database()->getProvider()) . '/config/overrides/' . $this->profile . '/install';
    $database_override_config_storage = new FileStorage($database_override_path, StorageInterface::DEFAULT_COLLECTION);

    foreach ($profile_config_storage->listAll() as $config_name) {
      if ($database_override_config_storage->exists($config_name)) {
        $result = $config_manager->diff($database_override_config_storage, $active_config_storage, $config_name);
      }
      else {
        $result = $config_manager->diff($profile_config_storage, $active_config_storage, $config_name);
      }
      $this->assertConfigDiff($result, $config_name, $skipped_config);
    }
  }

}
