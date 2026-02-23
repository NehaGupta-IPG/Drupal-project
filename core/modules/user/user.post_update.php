<?php

/**
 * @file
 * Post update functions for User module.
 */

use Drupal\system\Entity\Action;

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
 * Add an action to send activation emails to multiple users.
 */
function user_post_update_add_send_action(): void {
  $action = Action::create([
    'id' => 'user_welcome_message_action',
    'type' => 'user',
    'label' => 'Send the welcome message to the selected user(s)',
    'configuration' => [],
    'plugin' => 'user_welcome_message_action',
  ]);
  $action->trustData()->save();
}
