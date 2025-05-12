<?php

/**
 * @file
 * Post update functions for Layout Builder.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;

/**
 * Implements hook_removed_post_updates().
 */
function layout_builder_removed_post_updates(): array {
  return [
    'layout_builder_post_update_rebuild_plugin_dependencies' => '9.0.0',
    'layout_builder_post_update_add_extra_fields' => '9.0.0',
    'layout_builder_post_update_section_storage_context_definitions' => '9.0.0',
    'layout_builder_post_update_overrides_view_mode_annotation' => '9.0.0',
    'layout_builder_post_update_cancel_link_to_discard_changes_form' => '9.0.0',
    'layout_builder_post_update_remove_layout_is_rebuilding' => '9.0.0',
    'layout_builder_post_update_routing_entity_form' => '9.0.0',
    'layout_builder_post_update_discover_blank_layout_plugin' => '9.0.0',
    'layout_builder_post_update_routing_defaults' => '9.0.0',
    'layout_builder_post_update_discover_new_contextual_links' => '9.0.0',
    'layout_builder_post_update_fix_tempstore_keys' => '9.0.0',
    'layout_builder_post_update_section_third_party_settings_schema' => '9.0.0',
    'layout_builder_post_update_layout_builder_dependency_change' => '9.0.0',
    'layout_builder_post_update_update_permissions' => '9.0.0',
    'layout_builder_post_update_make_layout_untranslatable' => '9.0.0',
    'layout_builder_post_update_override_entity_form_controller' => '10.0.0',
    'layout_builder_post_update_section_storage_context_mapping' => '10.0.0',
    'layout_builder_post_update_tempstore_route_enhancer' => '10.0.0',
    'layout_builder_post_update_timestamp_formatter' => '11.0.0',
    'layout_builder_post_update_enable_expose_field_block_feature_flag' => '11.0.0',
  ];
}

/**
 * Initialize the show_when_empty setting for FieldBlock blocks.
 */
function layout_builder_post_update_initialize_field_block_shown_when_empty(?array &$sandbox = NULL): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'entity_view_display', function (EntityViewDisplayInterface $entity_view_display): bool {
    $update = FALSE;
    if ($entity_view_display instanceof LayoutEntityDisplayInterface && $entity_view_display->isLayoutBuilderEnabled()) {
      foreach ($entity_view_display->getSections() as $section) {
        foreach ($section->getComponents() as $component) {
          if (str_starts_with($component->getPluginId(), 'field_block:')) {
            $configuration = $component->get('configuration');

            if (isset($configuration['hide_for_empty'])) {
              $configuration['show_when_empty'] = !$configuration['hide_for_empty'];
              unset($configuration['hide_for_empty']);
              $component->setConfiguration($configuration);
              $update = TRUE;
            }

            if (!isset($configuration['show_when_empty'])) {
              $configuration['show_when_empty'] = FALSE;
              $component->setConfiguration($configuration);
              $update = TRUE;
            }
          }
        }
      }
    }
    return $update;
  });
}
