<?php

/**
 * @file
 * Post-update functions for Locale module.
 */

/**
 * Implements hook_removed_post_updates().
 */
function locale_removed_post_updates(): array {
  return [
    'locale_post_update_clear_cache_for_old_translations' => '9.0.0',
  ];
}

/**
 * Grant "administer locale" permission to appropriate users.
 */
function locale_post_update_grant_admininster_locale_permission(): void {
  /** @var \Drupal\user\RoleInterface[] $roles */
  $roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();
  foreach ($roles as $role) {
    if ($role->hasPermission('translate interface')) {
      $role->grantPermission('administer locale')->save();
    }
  }
}
