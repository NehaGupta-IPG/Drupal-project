<?php

/**
 * @file
 * Post update functions for Views.
 */

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
    'views_post_update_views_data_argument_plugin_id' => '12.0.0',
    'views_post_update_format_plural' => '12.0.0',
    'views_post_update_update_remember_role_empty' => '12.0.0',
    'views_post_update_table_css_class' => '12.0.0',
    'views_post_update_block_items_per_page' => '12.0.0',
    'views_post_update_add_date_default_arguments' => '12.0.0',
  ];
}

/**
 * Clear cache to apply non-sortable counter field change.
 */
function views_post_update_counter_field_not_sortable(): void {
  // Empty update to trigger a cache rebuild so updated Views data is applied.
}

/**
 * Enable views_grid module for sites using the grid style plugin.
 */
function views_post_update_enable_views_grid_module(): void {
  $config_factory = \Drupal::configFactory();
  $module_installer = \Drupal::service('module_installer');

  // Check if views_grid module is already enabled.
  if (\Drupal::moduleHandler()->moduleExists('views_grid')) {
    return;
  }

  // Find all view configs that use the grid style plugin.
  $has_grid_views = FALSE;
  $view_configs = $config_factory->listAll('views.view.');

  foreach ($view_configs as $config_name) {
    $config = $config_factory->get($config_name);
    $displays = $config->get('display') ?? [];

    foreach ($displays as $display) {
      $style = $display['display_options']['style']['type'] ?? NULL;
      if ($style === 'grid') {
        $has_grid_views = TRUE;
        break 2;
      }
    }
  }

  // If any views use the grid style, enable the views_grid module.
  if ($has_grid_views) {
    $module_installer->install(['views_grid']);
    \Drupal::messenger()->addStatus('The Views Grid module has been automatically enabled because your site has views using the deprecated grid style plugin.');
  }
}
