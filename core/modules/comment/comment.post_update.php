<?php

/**
 * @file
 * Post update functions for the comment module.
 */

/**
 * Implements hook_removed_post_updates().
 */
function comment_removed_post_updates(): array {
  return [
    'comment_post_update_enable_comment_admin_view' => '9.0.0',
    'comment_post_update_add_ip_address_setting' => '9.0.0',
  ];
}

/**
 * Update the form heading for all comment types.
 */
function comment_post_update_form_heading_for_comment_types() {
  $config_factory = \Drupal::configFactory();
  foreach ($config_factory->listAll('comment.type.') as $type_name) {
    $type = $config_factory->getEditable($type_name);
    $type->set('form_heading', 'Add new comment')->save(TRUE);
  }
}
