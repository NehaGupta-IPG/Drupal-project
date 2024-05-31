<?php

/**
 * @file
 * Post update functions for Filter.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;

/**
 * Implements hook_removed_post_updates().
 */
function filter_removed_post_updates(): array {
  return [
    'filter_post_update_sort_filters' => '11.0.0',
    'filter_post_update_consolidate_filter_config' => '11.0.0',
  ];
}

/**
 * Removes disabled filters from existing filter format configurations.
 */
function filter_post_update_remove_disabled_filters(array &$sandbox) {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'filter_format');
}
