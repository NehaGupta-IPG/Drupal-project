<?php

/**
 * @file
 * Post update functions for Options module.
 */

/**
 * Add check_all_enabled setting to existing options_buttons widget instances.
 */
function options_post_update_options_buttons_check_all_setting(): void {
  $form_displays = \Drupal::entityTypeManager()
    ->getStorage('entity_form_display')
    ->loadMultiple();

  foreach ($form_displays as $form_display) {
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
    if ($changed) {
      $form_display->save();
    }
  }
}
