<?php

/**
 * @file
 * Post update functions for the comment module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;

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
 * Saves the dependency on the entity provider into comment_type entities.
 */
function comment_post_update_provider_dependency(&$sandbox = NULL): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'comment_type', function () {
    // Save entity to recalculate dependencies.
    return TRUE;
  });
}
