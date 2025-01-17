<?php

namespace Drupal\field_ui\FormElement;

use Drupal\config_translation\FormElement\ListElement;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\Element;

/**
 * Adds translatable labels to entity_display elements.
 */
class EntityDisplayElement extends ListElement {

  /**
   * {@inheritdoc}
   */
  public function getTranslationBuild(
    LanguageInterface $source_language,
    LanguageInterface $translation_language,
    $source_config,
    $translation_config,
    array $parents,
    $base_key = NULL,
  ): array {
    $parent_build = parent::getTranslationBuild($source_language, $translation_language,
      $source_config, $translation_config, $parents,
      $base_key);

    $target_type_id = $source_config['targetEntityType'];
    $bundle_name = $source_config['bundle'];
    $components = $source_config['content'];

    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
    $field_manager = \Drupal::service('entity_field.manager');
    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $field_definitions */
    $field_definitions = $field_manager->getFieldDefinitions($target_type_id, $bundle_name);

    $element_names = [];
    if (isset($parent_build['content'])) {
      $parent_build['content']['#collapsible'] = FALSE;
      $element_names = array_intersect(array_keys($components), Element::children($parent_build['content']), array_keys($field_definitions));
    }

    if (empty($element_names)) {
      unset($parent_build['content']);
      return $parent_build;
    }

    $this->addLabels($parent_build, $element_names, $components, $target_type_id, $bundle_name);

    return $parent_build;
  }

  /**
   * Adds labels to the components.
   *
   * @param array $parent_build
   *   Parent translation build data.
   * @param array $element_names
   *   Associative array of element names.
   * @param array $components
   *   The components.
   * @param string $target_type_id
   *   The target type id.
   * @param string $bundle_name
   *   The bundle name.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function addLabels(array &$parent_build, array $element_names, array $components, string $target_type_id, string $bundle_name): void {

    /** @var \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager */
    $field_type_manager = \Drupal::service('plugin.manager.field.field_type');
    /** @var \Drupal\Core\Field\WidgetPluginManager $field_widget_manager */
    $field_widget_manager = \Drupal::service('plugin.manager.field.widget');
    /** @var \Drupal\Core\Field\FormatterPluginManager $field_formatter_manager */
    $field_formatter_manager = \Drupal::service('plugin.manager.field.formatter');
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
    $field_manager = \Drupal::service('entity_field.manager');
    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $field_definitions */
    $field_definitions = $field_manager->getFieldDefinitions($target_type_id, $bundle_name);

    foreach ($element_names as $component_name) {
      $item = &$parent_build['content'][$component_name];
      /** @var \Drupal\Core\Field\FieldDefinitionInterface $definition */
      $definition = $field_definitions[$component_name] ?? NULL;
      if ($definition) {
        $field_type = $field_type_manager->getDefinition($definition->getType());

        $item['#title'] = $definition->getLabel();
        $item['#description'] = t("Field: %name, type: @type", [
          '%name' => $component_name,
          '@type' => $field_type['label'],
        ]);
        // Set open state to let user reach settings without additional clicks.
        if (isset($item['#open'])) {
          $item['#open'] = TRUE;
        }

        $component_type = $components[$component_name]['type'];
        if (isset($item['settings']['#open'])) {
          $item['settings']['#open'] = TRUE;
        }

        if (str_starts_with($this->element->getName(), 'core.entity_view_display')) {
          /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $entity */
          // Set formatter type name if available.
          $formatter_options = $field_formatter_manager->getOptions($definition->getType());
          if (isset($formatter_options[$component_type]) && isset($item['settings'])) {
            $item['settings']['#title'] = t("%label format settings", ['%label' => $formatter_options[$component_type]]);
          }
        }
        else {
          /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $entity */
          // Set widget name if available.
          $widget_options = $field_widget_manager->getOptions($definition->getType());
          if (isset($widget_options[$component_type]) && isset($item['settings'])) {
            $item['settings']['#title'] = t("%label widget settings", ['%label' => $widget_options[$component_type]]);
          }
        }

      }
    }

  }

}
