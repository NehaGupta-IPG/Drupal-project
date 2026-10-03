<?php

/**
 * @file
 * Post update functions for System.
 */

use Drupal\Core\Datetime\Plugin\Field\FieldWidget\TimestampDatetimeWidget;

/**
 * Implements hook_removed_post_updates().
 */
function system_removed_post_updates(): array {
  return [
    'system_post_update_recalculate_configuration_entity_dependencies' => '9.0.0',
    'system_post_update_add_region_to_entity_displays' => '9.0.0',
    'system_post_update_hashes_clear_cache' => '9.0.0',
    'system_post_update_timestamp_plugins' => '9.0.0',
    'system_post_update_classy_message_library' => '9.0.0',
    'system_post_update_field_type_plugins' => '9.0.0',
    'system_post_update_field_formatter_entity_schema' => '9.0.0',
    'system_post_update_fix_jquery_extend' => '9.0.0',
    'system_post_update_change_action_plugins' => '9.0.0',
    'system_post_update_change_delete_action_plugins' => '9.0.0',
    'system_post_update_language_item_callback' => '9.0.0',
    'system_post_update_extra_fields' => '9.0.0',
    'system_post_update_states_clear_cache' => '9.0.0',
    'system_post_update_add_expand_all_items_key_in_system_menu_block' => '9.0.0',
    'system_post_update_clear_menu_cache' => '9.0.0',
    'system_post_update_layout_plugin_schema_change' => '9.0.0',
    'system_post_update_entity_reference_autocomplete_match_limit' => '9.0.0',
    'system_post_update_extra_fields_form_display' => '10.0.0',
    'system_post_update_uninstall_simpletest' => '10.0.0',
    'system_post_update_uninstall_entity_reference_module' => '10.0.0',
    'system_post_update_entity_revision_metadata_bc_cleanup' => '10.0.0',
    'system_post_update_uninstall_classy' => '10.0.0',
    'system_post_update_uninstall_stable' => '10.0.0',
    'system_post_update_claro_dropbutton_variants' => '10.0.0',
    'system_post_update_schema_version_int' => '10.0.0',
    'system_post_update_delete_rss_settings' => '10.0.0',
    'system_post_update_remove_key_value_expire_all_index' => '10.0.0',
    'system_post_update_service_advisory_settings' => '10.0.0',
    'system_post_update_delete_authorize_settings' => '10.0.0',
    'system_post_update_sort_all_config' => '10.0.0',
    'system_post_update_enable_provider_database_driver' => '10.0.0',
    'system_post_update_linkset_settings' => '11.0.0',
    'system_post_update_enable_password_compatibility' => '11.0.0',
    'system_post_update_remove_asset_entries' => '11.0.0',
    'system_post_update_remove_asset_query_string' => '11.0.0',
    'system_post_update_add_description_to_entity_view_mode' => '11.0.0',
    'system_post_update_add_description_to_entity_form_mode' => '11.0.0',
    'system_post_update_set_blank_log_url_to_null' => '11.0.0',
    'system_post_update_mailer_dsn_settings' => '11.0.0',
    'system_post_update_mailer_structured_dsn_settings' => '11.0.0',
    'system_post_update_amend_config_sync_readme_url' => '11.0.0',
    'system_post_update_mail_notification_setting' => '11.0.0',
    'system_post_update_set_cron_logging_setting_to_boolean' => '11.0.0',
    'system_post_update_move_development_settings_to_keyvalue' => '11.0.0',
    'system_post_update_add_langcode_to_all_translatable_config' => '11.0.0',
    'system_post_update_convert_empty_country_and_timezone_settings_to_null' => '12.0.0',
    'system_post_update_sdc_uninstall' => '12.0.0',
    'system_post_update_remove_rss_cdata_subscriber' => '12.0.0',
    'system_post_update_remove_path_key' => '12.0.0',
    'system_post_update_convert_empty_description_entity_form_modes_to_null' => '12.0.0',
    'system_post_update_delete_rss_config' => '12.0.0',
  ];
}

/**
 * Migrate to new compress setting.
 */
function system_post_update_migrate_compress_setting(): void {
  $performanceConfig = \Drupal::configFactory()->getEditable('system.performance');
  $performanceConfig->set('css.compress', (bool) $performanceConfig->get('css.gzip'));
  $performanceConfig->set('js.compress', (bool) $performanceConfig->get('js.gzip'));
  $performanceConfig->clear('css.gzip');
  $performanceConfig->clear('js.gzip');
  $performanceConfig->save();
}

/**
 * Remove non-admin routes state key.
 */
function system_post_update_remove_non_admin_routes_state(): void {
  \Drupal::state()->delete('routing.non_admin_routes');
}

/**
 * Remove the admin_compact_mode config.
 */
function system_post_update_clear_admin_compact_mode_config(): void {
  $config = \Drupal::configFactory()->getEditable('system.site');
  $config->clear('admin_compact_mode');
  $config->save();
}

/**
 * Set default use_current_time setting for all datetime timestamp form widgets.
 */
function system_post_update_update_datetime_timestamp_field_widget_current_time(): void {
  $form_modes = \Drupal::service('entity_display.repository')->getAllFormModes();
  $entity_form_display_storage = \Drupal::entityTypeManager()->getStorage('entity_form_display');
  $updated_form_ids = [];

  $entity_types = array_keys(\Drupal::entityTypeManager()->getDefinitions());
  foreach ($entity_types as $entity_type) {
    $entity_form_modes = array_merge(['default'], array_keys($form_modes[$entity_type] ?? []));

    $bundles = array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type));
    foreach ($bundles as $bundle) {
      // Build list of entity form display IDs, and then query to determine
      // which ones are valid.
      foreach ($entity_form_modes as $form_mode) {
        $candidate_ids[] = $entity_type . '.' . $bundle . '.' . $form_mode;
      }
      $results = $entity_form_display_storage->getQuery()
        ->condition('id', $candidate_ids)
        ->condition('status', TRUE)
        ->execute();

      // Iterate through each valid form display ID and load any that we have
      // not previously updated.
      foreach ($results as $candidate_id) {
        if (in_array($candidate_id, $updated_form_ids)) {
          continue;
        }
        $form_display = $entity_form_display_storage->load($candidate_id);
        $changed = FALSE;

        // Iterate through all widgets and update if we find a datetime
        // timestamp.
        $widgets = $form_display->get('content');
        foreach ($widgets as &$widget) {
          if (($widget['type'] ?? '') === 'datetime_timestamp' && empty($widget['settings']['use_current_time'])) {
            $widget['settings']['use_current_time'] = TimestampDatetimeWidget::TIMESTAMP_OPTION_ON_SUBMISSION;
            $changed = TRUE;
          }
        }

        // Update form display for widgets that have changed.
        if ($changed) {
          $form_display->set('content', $widgets);
          $form_display->save();
          $updated_form_ids[] = $candidate_id;
        }
      }
    }
  }
}
