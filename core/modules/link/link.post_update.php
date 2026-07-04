<?php

/**
 * @file
 * Contains post update hooks for link module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Populate new 'handler' settings for the link widget.
 */
function link_post_update_widget_handler_settings(&$sandbox = NULL): void {
  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  $callback = function (FieldConfigInterface $field) {
    if ($field->getType() == 'link') {
      // Set the default handler for the field instance here to avoid
      // trigger_error on presave hook.
      $field->setSetting('handler', 'default');
      $field->setSetting('handler_settings', []);
      return TRUE;
    }

    return FALSE;
  };

  $config_entity_updater->update($sandbox, 'field_config', $callback);
}

/**
 * Populate new 'match_limit' and 'match_operator' settings for the link widget.
 */
function link_post_update_match_settings(&$sandbox = NULL): void {
  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  /** @var \Drupal\Core\Field\WidgetPluginManager $field_widget_manager */
  $field_widget_manager = \Drupal::service('plugin.manager.field.widget');

  $callback = function (EntityDisplayInterface $display) use ($field_widget_manager) {
    $needs_update = FALSE;
    foreach ($display->getComponents() as $field_name => $component) {
      if (empty($component['type'])) {
        continue;
      }

      $plugin_definition = $field_widget_manager->getDefinition($component['type'], FALSE);
      if (is_a($plugin_definition['class'], LinkWidget::class, TRUE)) {
        // Set the default match settings for the link widget here to avoid
        // trigger_error on presave hook.
        $component['settings']['match_operator'] = 'CONTAINS';
        $component['settings']['match_limit'] = 10;
        $display->setComponent($field_name, $component);
        $needs_update = TRUE;
      }
    }

    return $needs_update;
  };

  $config_entity_updater->update($sandbox, 'entity_form_display', $callback);
}
