<?php

namespace Drupal\link\Hook;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Provides deprecation warnings in the case of missing new field settings.
 *
 * @todo Remove this class in Drupal 12.0.0
 *   https://www.drupal.org/project/drupal/issues/3373258
 */
class FieldSettingsDeprecationHooks {

  /**
   * Implements hook_ENTITY_TYPE_presave() for field_config entities.
   */
  #[Hook('ENTITY_TYPE_presave')]
  public function presaveFieldConfig(FieldConfigInterface $field): void {
    if ($field->getType() !== 'link') {
      return;
    }
    if (empty($field->getSetting('handler'))) {
      @trigger_error("Having empty or not set 'handler' and 'handler_settings' settings is deprecated in drupal:11.3.0 and will be mandatory in drupal:12.0.0. Configure field {$field->getLabel()} for {$field->getTargetBundle()} ({$field->getEntityType()->getLabel()}). See https://www.drupal.org/node/3375174", E_USER_DEPRECATED);
      $field->setSetting('handler', 'default');
      $field->setSetting('handler_settings', []);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for entity_form_display entities.
   */
  #[Hook('ENTITY_TYPE_presave')]
  public function presaveEntityFormDisplay(EntityFormDisplayInterface $display): void {
    /** @var \Drupal\Core\Field\WidgetPluginManager $field_widget_manager */
    $field_widget_manager = \Drupal::service('plugin.manager.field.widget');

    foreach ($display->getComponents() as $field_name => $component) {
      if (empty($component['type'])) {
        continue;
      }

      $plugin_definition = $field_widget_manager->getDefinition($component['type'], FALSE);
      if (!is_a($plugin_definition['class'], LinkWidget::class, TRUE)) {
        continue;
      }

      if (!isset($component['settings']['match_limit'])) {
        @trigger_error("Having empty or not set 'match_limit' and 'match_operator' is deprecated in drupal:11.3.0 and will be mandatory in drupal:12.0.0. Configure field {$display->label()} for {$display->getTargetBundle()} ({$display->getTargetEntityTypeId()}). See https://www.drupal.org/node/3375174", E_USER_DEPRECATED);
        $component['settings']['match_operator'] = 'CONTAINS';
        $component['settings']['match_limit'] = 10;
        $display->setComponent($field_name, $component);
      }
    }
  }

}
