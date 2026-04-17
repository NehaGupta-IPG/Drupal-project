<?php

/**
 * @file
 * Post update functions for Media.
 */

use Drupal\Core\StringTranslation\TranslatableMarkup;

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
  ];
}

/**
 * Empty update function to clear the Views data cache.
 */
function media_post_update_media_author_views_filter_update(): void {
  // Empty update function to clear the Views data cache.
}

/**
 * Disable contextual links for media embeds in all text formats.
 */
function media_post_update_add_show_contextual_links_as_false(&$sandbox = NULL): TranslatableMarkup {
  // Initialize batch variables if this is the first run.
  if (!isset($sandbox['total'])) {
    // Load all filter_format configurations.
    $config_names = \Drupal::service('config.storage')->listAll('filter.format.');
    $sandbox['config_names'] = $config_names;
    $sandbox['total'] = count($sandbox['config_names']);
    $sandbox['progress'] = 0;

    // In case there are no configurations to process.
    if ($sandbox['total'] == 0) {
      $sandbox['total'] = 1;
      $sandbox['progress'] = 1;
    }
  }

  // Process configurations in chunks of 50.
  $config_names = array_splice($sandbox['config_names'], 0, 50);

  foreach ($config_names as $config_name) {
    $config = \Drupal::service('config.factory')->getEditable($config_name);
    $filters = $config->get('filters');

    if (isset($filters['media_embed'])) {
      $media_embed_settings = $filters['media_embed'];
      if (empty($media_embed_settings['settings']['show_contextual_links'])) {
        $media_embed_settings['settings']['show_contextual_links'] = FALSE;
        // Update the settings in the filters array.
        $filters['media_embed'] = $media_embed_settings;
        $config->set('filters', $filters);
        $config->save();
      }
    }

    $sandbox['progress']++;
  }

  // Determine if the batch process is complete.
  $sandbox['#finished'] = ($sandbox['progress'] / $sandbox['total']);

  return new TranslatableMarkup('Processed Filter Formats (@count/@total)', [
    '@count' => $sandbox['progress'],
    '@total' => $sandbox['total'],
  ]);
}
