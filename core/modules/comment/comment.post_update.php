<?php

/**
 * @file
 * Post update functions for the comment module.
 */

use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
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
 * Set the default value of 'thread_limit' setting for all comment fields.
 */
function comment_post_update_add_thread_limit_field_setting(?array &$sandbox = NULL): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'field_config', function (FieldConfigInterface $field_config): bool {
    if ($field_config->getType() === 'comment') {
      $field_config->setSetting('thread_limit', [
        'depth' => 2,
        'mode' => CommentItemInterface::THREAD_DEPTH_REPLY_MODE_ALLOW,
      ]);
      return TRUE;
    }
    return FALSE;
  });
}
