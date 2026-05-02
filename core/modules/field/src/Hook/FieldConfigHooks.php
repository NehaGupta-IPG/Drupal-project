<?php

namespace Drupal\field\Hook;

use Drupal\field\ConfigImporterFieldPurger;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Config hook implementations for field.
 */
class FieldConfigHooks {

  /**
   * Implements hook_config_import_steps_alter().
   */
  #[Hook('config_import_steps_alter')]
  public function configImportStepsAlter(&$sync_steps, ConfigImporter $config_importer): void {
    $field_storages = ConfigImporterFieldPurger::getFieldStoragesToPurge($config_importer->getStorageComparer()->getSourceStorage()->read('core.extension'), $config_importer->getStorageComparer()->getChangelist('delete'));
    if ($field_storages) {
      // Add a step to the beginning of the configuration synchronization
      // process to purge field data where the module that provides the field is
      // being uninstalled.
      array_unshift($sync_steps, ['\Drupal\field\ConfigImporterFieldPurger', 'process']);
    }
  }

}
