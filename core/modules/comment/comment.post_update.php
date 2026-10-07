<?php

/**
 * @file
 * Post update functions for the comment module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\field\FieldConfigInterface;

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
 * Set default value for thread reply depth.
 */
function comment_post_update_thread_reply_depth(?array &$sandbox = NULL): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update(
    $sandbox,
    'field_config',
    function (FieldConfigInterface $field_config): bool {
      if ($field_config->getType() === 'comment') {
        $field_config->setSetting('thread_reply_depth', -1);
        return TRUE;
      }
      return FALSE;
    },
  );
}
