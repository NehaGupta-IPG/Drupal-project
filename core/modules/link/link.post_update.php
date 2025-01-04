<?php

/**
 * @file
 * Post update functions for the Link module.
 */

/**
 * Add the 'allowed_protocols' setting to all existing link fields.
 */
function link_post_update_add_allowed_protocols(&$sandbox): void {
  if (!isset($sandbox['current'])) {
    $sandbox['current'] = 0;
  }

  // Load all field configurations.
  $field_config_storage = \Drupal::entityTypeManager()->getStorage('field_config');
  $field_configs = $field_config_storage->loadMultiple();

  // Process all configurations in batches.
  $total = count($field_configs);
  foreach (array_slice($field_configs, $sandbox['current'], 10) as $field_config) {
    // Process only link fields.
    if ($field_config->get('field_type') === 'link') {
      $settings = $field_config->get('settings');
      if (!isset($settings['allowed_protocols'])) {
        // Add the new parameter with a default empty value.
        $settings['allowed_protocols'] = [];
        $field_config->set('settings', $settings);
        $field_config->save();
      }
    }
    $sandbox['current']++;
  }

  // Finish the batch.
  if ($sandbox['current'] < $total) {
    $sandbox['#finished'] = $sandbox['current'] / $total;
  }
  else {
    $sandbox['#finished'] = 1;
  }
}
