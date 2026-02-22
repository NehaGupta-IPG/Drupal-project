<?php

namespace Drupal\telephone;

/**
 * Telephone config class to process telephone config in batch.
 */
class TelephoneConfig {

  /**
   * Function to process telephone config in batch.
   */
  public static function processTelephoneConfig($configName, &$context) {
    $message = 'Updating ...';
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $field_definitions = $entity_type_manager->getStorage('field_storage_config')
      ->loadByProperties(['type' => 'telephone']);
    $config = \Drupal::configFactory()->getEditable($configName);
    foreach ($field_definitions as $key => $field_definition) {
      $fieldName = explode('.', $key)[1];
      if ($config->get('content') && !empty($config->get('content.' . $fieldName))) {
        if (!$config->get('content.' . $fieldName . '.settings.size')) {
          $ex_settings = $config->get('content.' . $fieldName . '.settings');
          $size_setting = ['size' => 60];
          $new_settings = array_merge($size_setting, $ex_settings);
          $config->set('content.' . $fieldName . '.settings', $new_settings)->save();
          $results[] = $config->set('content.' . $fieldName . '.settings', $new_settings)->save();
          $context['message'] = $message;
          $context['results'][] = $configName . '-' . $fieldName;
        }
      }
    }
  }

  /**
   * Function that executes post batch processing.
   */
  public static function exportFinished($success, $results, $operations) {
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        count($results),
        'One post processed.', '@count posts processed.'
      );
    }
    else {
      $message = t('Finished with an error.');
    }
    \Drupal::messenger()->addMessage($message);
  }

}
