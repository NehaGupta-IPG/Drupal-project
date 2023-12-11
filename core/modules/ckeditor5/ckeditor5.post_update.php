<?php

/**
 * @file
 * Post update functions for CKEditor 5.
 */

use Drupal\Core\Config\FileStorage;

// cspell:ignore multiblock

/**
 * Implements hook_removed_post_updates().
 */
function ckeditor5_removed_post_updates(): array {
  return [
    'ckeditor5_post_update_alignment_buttons' => '10.0.0',
    'ckeditor5_post_update_image_toolbar_item' => '11.0.0',
    'ckeditor5_post_update_plugins_settings_export_order' => '11.0.0',
    'ckeditor5_post_update_code_block' => '11.0.0',
    'ckeditor5_post_update_list_multiblock' => '11.0.0',
    'ckeditor5_post_update_list_start_reversed' => '11.0.0',
  ];
}

/**
 * Creates the ckeditor_inline view mode for media entities.
 */
function ckeditor5_post_update_create_ckeditor_inline_view_mode() {
  $config_path = \Drupal::service('extension.list.module')->getPath('ckeditor5') . '/config/optional';
  $source = new FileStorage($config_path);
  $entity_type_manager = \Drupal::entityTypeManager();
  $module_handler = \Drupal::moduleHandler();
  /** @var \Drupal\Core\Config\StorageInterface $config_storage */
  $config_storage = \Drupal::service('config.storage');

  if ($module_handler->moduleExists('media') && !$config_storage->exists('core.entity_view_mode.media.ckeditor_inline')) {
    $entity_type_manager->getStorage('entity_view_mode')
      ->create($source->read('core.entity_view_mode.media.ckeditor_inline'))
      ->save();
  }
}

/**
 * Amends the filter format settings to allow the drupal-media-inline tag.
 */
function ckeditor5_post_update_allow_inline_media_element() {
  $formats = filter_formats();
  foreach ($formats as $format_id => $format) {
    $config = \Drupal::configFactory()->getEditable('filter.format.' . $format_id);
    $filters = $config->get('filters');
    if (\array_key_exists('media_embed', $filters)
      && \array_key_exists('filter_html', $filters)
      && !\str_contains($filters['filter_html']['settings']['allowed_html'], 'drupal-media-inline')) {
      $filters['filter_html']['settings']['allowed_html'] .= ' <drupal-media-inline data-entity-type data-entity-uuid alt data-view-mode data-caption data-align>';
      $config->set('filters', $filters);
      $config->save();
    }
  }
}
