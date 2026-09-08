<?php

/**
 * @file
 * Post update functions for Options module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;

/**
 * Add check_all_enabled setting to existing options_buttons widget instances.
 */
function options_post_update_options_buttons_check_all_setting(array &$sandbox): void {
  $callback = function (EntityFormDisplayInterface $form_display): bool {
    $changed = FALSE;
    foreach ($form_display->getComponents() as $field_name => $component) {
      if (($component['type'] ?? '') === 'options_buttons') {
        if (!array_key_exists('check_all_enabled', $component['settings'] ?? [])) {
          $component['settings']['check_all_enabled'] = FALSE;
          $form_display->setComponent($field_name, $component);
          $changed = TRUE;
        }
      }
    }
    return $changed;
  };
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'entity_form_display', $callback);
}
