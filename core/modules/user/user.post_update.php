<?php

/**
 * @file
 * Post update functions for User module.
 */

/**
 * Implements hook_removed_post_updates().
 */
function user_removed_post_updates(): array {
  return [
    'user_post_update_enforce_order_of_permissions' => '9.0.0',
    'user_post_update_update_roles' => '10.0.0',
    'user_post_update_sort_permissions' => '11.0.0',
    'user_post_update_sort_permissions_again' => '11.0.0',
  ];
}

/**
 * Creates the new user cancellation methods configuration.
 */
function user_post_update_configure_cancel_options(): void {
  $config = \Drupal::configFactory()->getEditable('user.settings');
  $methods = user_cancel_methods();
  foreach (array_keys($methods['#options']) as $method_name) {
    $config->set('cancel_method_options.' . $method_name, TRUE);
  }
  $config->save(TRUE);
}
