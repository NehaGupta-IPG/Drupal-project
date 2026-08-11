<?php

/**
 * @file
 * Post update functions for the Content Moderation module.
 */

use Drupal\Core\Config\FileStorage;

/**
 * Implements hook_removed_post_updates().
 */
function content_moderation_removed_post_updates(): array {
  return [
    'content_moderation_post_update_update_cms_default_revisions' => '9.0.0',
    'content_moderation_post_update_set_default_moderation_state' => '9.0.0',
    'content_moderation_post_update_set_views_filter_latest_translation_affected_revision' => '9.0.0',
    'content_moderation_post_update_entity_display_dependencies' => '9.0.0',
    'content_moderation_post_update_views_field_plugin_id' => '9.0.0',
    'content_moderation_post_update_add_index_content_moderation_state_field_revision_moderation_state' => '12.0.0',
  ];
}

/**
 * Install moderated block content view.
 */
function content_moderation_post_update_install_moderated_block_content_view() :void {
  $dir = \Drupal::service('extension.list.module')->getPath('content_moderation') . '/config/optional';
  $fileStorage = new FileStorage($dir);
  $config = $fileStorage->read('views.view.moderated_blocks');

  /** @var \Drupal\Core\Config\Entity\ConfigEntityStorage $storage */
  $storage = \Drupal::entityTypeManager()->getStorage('view');

  /** @var \Drupal\views\Entity\View $view */
  $view = $storage->create($config);
  $view->save();
}
