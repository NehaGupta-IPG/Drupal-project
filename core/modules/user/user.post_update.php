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
 * Update config for change mail notifications.
 */
function user_post_update_mail_change(): void {
  $config_factory = \Drupal::service('config.factory');

  $config_factory->getEditable('user.settings')
    ->set('notify.mail_change_notification', FALSE)
    ->set('notify.mail_change_verification', FALSE)
    ->set('mail_change_timeout', 86400)
    ->save(TRUE);

  $mail_change_notification = [
    'body' => "[user:display-name],\n\nA request to change your email address has been made at [site:name]. In order to complete the change you will need to follow the instructions sent to your new email address within 24 hours.\n\nIf you did not intend to make this change, contact [site:mail].\n\n--  [site:name] team",
    'subject' => 'Email change for [user:display-name] at [site:name]',
  ];
  $mail_change_verification = [
    'body' => "[user:display-name],\n\nA request to change your email address has been made at [site:name]. You need to verify the change by clicking on the link below or copying and pasting it in your browser:\n\n[user:mail-change-url]\n\nThis link can only be used once and it expires after 24 hours. If not used, your email address at [site:name] will not change.\n\n--  [site:name] team",
    'subject' => 'Email change for [user:display-name] at [site:name]',
  ];

  $config_factory->getEditable('user.mail')
    ->set('mail_change_notification', $mail_change_notification)
    ->set('mail_change_verification', $mail_change_verification)
    ->save(TRUE);
}
