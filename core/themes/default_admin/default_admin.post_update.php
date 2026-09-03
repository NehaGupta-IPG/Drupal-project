<?php

/**
 * @file
 * Post update functions for Default Admin.
 */

/**
 * Renames legacy focus color preset values in theme and user settings.
 */
function default_admin_post_update_rename_focus_color_presets(): void {
  $renamed = [
    'gin' => 'default',
    'claro' => 'legacy_green',
  ];

  $config = \Drupal::configFactory()->getEditable('default_admin.settings');
  $preset = $config->get('preset_focus_color');
  if (is_string($preset) && isset($renamed[$preset])) {
    $config->set('preset_focus_color', $renamed[$preset])->save();
  }

  if (!\Drupal::hasService('user.data')) {
    return;
  }
  /** @var \Drupal\user\UserDataInterface $user_data */
  $user_data = \Drupal::service('user.data');
  foreach ($user_data->get('default_admin') as $uid => $data) {
    // User overrides are stored as one array under the 'settings' key.
    $preset = $data['settings']['preset_focus_color'] ?? NULL;
    if (is_string($preset) && isset($renamed[$preset])) {
      $data['settings']['preset_focus_color'] = $renamed[$preset];
      $user_data->set('default_admin', $uid, 'settings', $data['settings']);
    }
    // Older overrides were stored under one key per setting.
    $preset = $data['preset_focus_color'] ?? NULL;
    if (is_string($preset) && isset($renamed[$preset])) {
      $user_data->set('default_admin', $uid, 'preset_focus_color', $renamed[$preset]);
    }
  }
}

/**
 * Removes the legacy 'enable_dark_mode' setting from theme configuration.
 */
function default_admin_post_update_remove_enable_dark_mode(): void {
  \Drupal::configFactory()->getEditable('default_admin.settings')
    ->clear('enable_dark_mode')
    ->save();
}

/**
 * Migrates per-user dark mode overrides to the color_scheme user field.
 */
function default_admin_post_update_migrate_dark_mode_to_color_scheme(): void {
  $mapping = [
    '0' => 'light',
    '1' => 'dark',
    'auto' => 'auto',
  ];

  $database = \Drupal::database();
  $result = $database->select('users_data', 'ud')
    ->fields('ud', ['uid', 'name', 'value', 'serialized'])
    ->condition('ud.module', 'default_admin')
    ->condition('ud.name', ['settings', 'enable_dark_mode'], 'IN')
    ->execute();

  // Collect the legacy value per user. The 'settings' array takes precedence
  // over the scalar 'enable_dark_mode' row, matching Settings::get().
  $legacy_value = [];
  $user_data = \Drupal::service('user.data');
  foreach ($result as $row) {
    $uid = (int) $row->uid;
    if ($uid === 0) {
      continue;
    }

    if ($row->name === 'settings' && $row->serialized) {
      $settings = unserialize($row->value);
      if (is_array($settings) && array_key_exists('enable_dark_mode', $settings)) {
        if ($settings['enable_dark_mode'] !== NULL) {
          $legacy_value[$uid] = $settings['enable_dark_mode'];
        }
        unset($settings['enable_dark_mode']);
        if ($settings === []) {
          $user_data->delete('default_admin', $uid, 'settings');
        }
        else {
          $user_data->set('default_admin', $uid, 'settings', $settings);
        }
      }
    }
    elseif ($row->name === 'enable_dark_mode') {
      // The scalar row only applies when no 'settings' value is present.
      $legacy_value[$uid] ??= $row->value;
    }
  }
  $user_data->delete('default_admin', NULL, 'enable_dark_mode');

  $storage = \Drupal::entityTypeManager()->getStorage('user');
  foreach ($legacy_value as $uid => $value) {
    $key = (string) $value;
    if ($key !== 'auto' && isset($mapping[$key])) {
      $user = $storage->load($uid);
      if ($user) {
        $user->set('color_scheme', $mapping[$key])->save();
      }
    }
  }
}
