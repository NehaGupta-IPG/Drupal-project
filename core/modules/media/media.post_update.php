<?php

/**
 * @file
 * Post update functions for Media.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\media\MediaConfigUpdater;

/**
 * Implements hook_removed_post_updates().
 */
function media_removed_post_updates(): array {
  return [
    'media_post_update_collection_route' => '9.0.0',
    'media_post_update_storage_handler' => '9.0.0',
    'media_post_update_enable_standalone_url' => '9.0.0',
    'media_post_update_add_status_extra_filter' => '9.0.0',
    'media_post_update_modify_base_field_author_override' => '10.0.0',
    'media_post_update_oembed_loading_attribute' => '11.0.0',
    'media_post_update_set_blank_iframe_domain_to_null' => '11.0.0',
    'media_post_update_remove_mappings_targeting_source_field' => '11.0.0',
    'media_post_update_media_author_views_filter_update' => '12.0.0',
  ];
}

/**
 * Add the image preload setting to image field formatter instances.
 */
function media_post_update_preload_setting(array &$sandbox = []): void {
  $media_config_updater = \Drupal::classResolver(MediaConfigUpdater::class);
  assert($media_config_updater instanceof MediaConfigUpdater);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'entity_view_display', function (EntityViewDisplayInterface $view_display) use ($media_config_updater): bool {
    return $media_config_updater->processImagePreload($view_display);
  });
}
