<?php

/**
 * @file
 * Post update functions for Views.
 */

use Drupal\block\BlockInterface;
use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\views\ViewEntityInterface;
use Drupal\views\ViewsConfigUpdater;

/**
 * Implements hook_removed_post_updates().
 */
function views_removed_post_updates(): array {
  return [
    'views_post_update_update_cacheability_metadata' => '9.0.0',
    'views_post_update_cleanup_duplicate_views_data' => '9.0.0',
    'views_post_update_field_formatter_dependencies' => '9.0.0',
    'views_post_update_taxonomy_index_tid' => '9.0.0',
    'views_post_update_serializer_dependencies' => '9.0.0',
    'views_post_update_boolean_filter_values' => '9.0.0',
    'views_post_update_grouped_filters' => '9.0.0',
    'views_post_update_revision_metadata_fields' => '9.0.0',
    'views_post_update_entity_link_url' => '9.0.0',
    'views_post_update_bulk_field_moved' => '9.0.0',
    'views_post_update_filter_placeholder_text' => '9.0.0',
    'views_post_update_views_data_table_dependencies' => '9.0.0',
    'views_post_update_table_display_cache_max_age' => '9.0.0',
    'views_post_update_exposed_filter_blocks_label_display' => '9.0.0',
    'views_post_update_make_placeholders_translatable' => '9.0.0',
    'views_post_update_limit_operator_defaults' => '9.0.0',
    'views_post_update_remove_core_key' => '9.0.0',
    'views_post_update_field_names_for_multivalue_fields' => '10.0.0',
    'views_post_update_configuration_entity_relationships' => '10.0.0',
    'views_post_update_rename_default_display_setting' => '10.0.0',
    'views_post_update_remove_sorting_global_text_field' => '10.0.0',
    'views_post_update_title_translations' => '10.0.0',
    'views_post_update_sort_identifier' => '10.0.0',
    'views_post_update_provide_revision_table_relationship' => '10.0.0',
    'views_post_update_image_lazy_load' => '10.0.0',
    'views_post_update_boolean_custom_titles' => '11.0.0',
    'views_post_update_oembed_eager_load' => '11.0.0',
    'views_post_update_responsive_image_lazy_load' => '11.0.0',
    'views_post_update_timestamp_formatter' => '11.0.0',
    'views_post_update_fix_revision_id_part' => '11.0.0',
    'views_post_update_add_missing_labels' => '11.0.0',
    'views_post_update_remove_skip_cache_setting' => '11.0.0',
    'views_post_update_remove_default_argument_skip_url' => '11.0.0',
    'views_post_update_taxonomy_filter_user_context' => '11.0.0',
    'views_post_update_pager_heading' => '11.0.0',
    'views_post_update_rendered_entity_field_cache_metadata' => '11.0.0',
  ];
}

/**
 * Post update configured views for entity reference argument plugin IDs.
 */
function views_post_update_views_data_argument_plugin_id(?array &$sandbox = NULL): void {
  /** @var \Drupal\views\ViewsConfigUpdater $view_config_updater */
  $view_config_updater = \Drupal::service(ViewsConfigUpdater::class);
  $view_config_updater->setDeprecationsEnabled(FALSE);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($view_config_updater): bool {
    return $view_config_updater->needsEntityArgumentUpdate($view);
  });
}

/**
 * Updates the format plural option for those views using aggregation.
 */
function views_post_update_format_plural(?array &$sandbox = NULL): void {
  /** @var \Drupal\views\ViewsConfigUpdater $view_config_updater */
  $view_config_updater = \Drupal::classResolver(ViewsConfigUpdater::class);
  $view_config_updater->setDeprecationsEnabled(FALSE);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($view_config_updater): bool {
    return $view_config_updater->needsFormatPluralUpdate($view);
  });
}

/**
 * Clean-up empty remember_roles display settings for views filters.
 */
function views_post_update_update_remember_role_empty(?array &$sandbox = NULL): void {
  /** @var \Drupal\views\ViewsConfigUpdater $view_config_updater */
  $view_config_updater = \Drupal::service(ViewsConfigUpdater::class);
  $view_config_updater->setDeprecationsEnabled(FALSE);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($view_config_updater): bool {
    return $view_config_updater->needsRememberRolesUpdate($view);
  });
}

/**
 * Adds a default table CSS class.
 */
function views_post_update_table_css_class(?array &$sandbox = NULL): void {
  /** @var \Drupal\views\ViewsConfigUpdater $view_config_updater */
  $view_config_updater = \Drupal::service(ViewsConfigUpdater::class);
  $view_config_updater->setDeprecationsEnabled(FALSE);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($view_config_updater): bool {
    return $view_config_updater->needsTableCssClassUpdate($view);
  });
}

/**
 * Defaults `items_per_page` to NULL in Views blocks.
 */
function views_post_update_block_items_per_page(?array &$sandbox = NULL): void {
  if (!\Drupal::moduleHandler()->moduleExists('block')) {
    return;
  }
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'block', function (BlockInterface $block): bool {
      if (str_starts_with($block->getPluginId(), 'views_block:')) {
        $settings = $block->get('settings');
        if ($settings['items_per_page'] === 'none') {
          $settings['items_per_page'] = NULL;
          $block->set('settings', $settings);
          return TRUE;
        }
      }
      return FALSE;
    });
}

/**
 * Adds 'style_options' and 'pager_options' to existing views.
 *
 * @param array &$sandbox
 *   A sandbox array used for batch processing.
 *
 * @return string|null
 *   A message indicating the result of the update, or null if the batch is
 *   still processing.
 */
function views_post_update_add_style_and_pager_display_options(?array &$sandbox = NULL): ?string {
  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $views_storage = \Drupal::entityTypeManager()->getStorage('view');
    $sandbox['view_ids'] = $views_storage->getQuery()->execute();
    $sandbox['max'] = count($sandbox['view_ids']);
  }

  $limit = 5;
  $views_storage = \Drupal::entityTypeManager()->getStorage('view');
  $view_ids_chunk = array_slice($sandbox['view_ids'], $sandbox['progress'], $limit);
  $views = $views_storage->loadMultiple($view_ids_chunk);

  foreach ($views as $view) {
    $changed = FALSE;
    foreach ($view->get('display') as $display_id => $display) {
      // Check and add 'style_options' if 'style' is set.
      if (isset($display['display_options']['style'])) {
        // Check for 'style_options'. Add if not set, preserve 'false'.
        if (!array_key_exists('style_options', $display['display_options'])) {
          $view->set("display.$display_id.display_options.style_options", TRUE);
          $changed = TRUE;
        }
      }
      // Check and add 'pager_options' if 'pager' is set.
      if (isset($display['display_options']['pager'])) {
        // Check for 'pager_options'. Add if not set, preserve 'false'.
        if (!array_key_exists('pager_options', $display['display_options'])) {
          $view->set("display.$display_id.display_options.pager_options", TRUE);
          $changed = TRUE;
        }
      }
    }
    if ($changed) {
      $view->save();
      \Drupal::logger('views')->notice('Updated view @id with new pager and style options.', ['@id' => $view->id()]);
    }
  }

  $sandbox['progress'] += count($view_ids_chunk);

  if ($sandbox['progress'] >= $sandbox['max']) {
    // Clear caches once after all batches are processed.
    drupal_flush_all_caches();
    return t('All views have been updated with new pager and style options.');
  }
  else {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['max'];
    return t('Processed @current out of @total views.', ['@current' => $sandbox['progress'], '@total' => $sandbox['max']]);
  }
}
