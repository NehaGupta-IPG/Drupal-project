<?php

namespace Drupal\layout_builder\FormElement;

use Drupal\Component\Plugin\PluginBase;
use Drupal\config_translation\FormElement\ListElement;
use Drupal\Core\Language\LanguageInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;

/**
 * Adds translatable labels to layout_builder elements.
 */
class LayoutBuilderDisplayElement extends ListElement {
  public static $data = NULL;

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
    if (!empty($parent_build['sections'])) {
      $build_element_names = $this->layoutBuilderGetElementNames($parent_build);
    }

    if (empty($build_element_names)) {
      return $parent_build;
    }
    self::setElementNames($build_element_names);
    $this->addLabels($parent_build, $build_element_names);
    return $parent_build;
  }

  /**
   * Returns the layout builder form element names.
   *
   * @param array $parent_build
   *   Parent translation build data.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function layoutBuilderGetElementNames(array $parent_build): array {
    // Configuration name will be also used as element name.
    $element_names = [];
    $element_name = $this->element->getParent()->getParent()->getName();
    $entity = LayoutBuilderEntityViewDisplay::load(str_replace("core.entity_view_display.", "", $element_name));
    /** @var \Drupal\layout_builder\Section $section */
    foreach ($entity->getSections() as $section_index => $section) {
      $section_components = $section->getComponents();
      if (empty($section_components)) {
        continue;
      }
      foreach ($section_components as $component) {
        if (!str_starts_with($component->getPluginId(), 'field_block:') && !str_starts_with($component->getPluginId(), 'extra_field_block:')) {
          continue;
        }
        if (!isset($component->get('configuration')['formatter']['settings']) || empty($component->get('configuration')['formatter']['settings'])) {
          continue;
        }
        if (!isset($parent_build['sections'][$section_index]['components'][$component->getUuid()]['configuration']['formatter']['settings'])) {
          continue;
        }
        try {
          [,,, $field_name] = explode(PluginBase::DERIVATIVE_SEPARATOR, $component->getPluginId(), 4);
        }
        catch (\Exception) {
          continue;
        }
        $element_names[] = $section_index . PluginBase::DERIVATIVE_SEPARATOR . $component->getUuid() . PluginBase::DERIVATIVE_SEPARATOR . $field_name;
      }
    }
    return $element_names;
  }

  /**
   * Adds labels to the components.
   *
   * @param array $parent_build
   *   Parent translation build data.
   * @param array $element_names
   *   Associative array of element names and layout builder flag.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function addLabels(array &$parent_build, array $element_names): void {

    /** @var \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager */
    $field_type_manager = \Drupal::service('plugin.manager.field.field_type');
    /** @var \Drupal\Core\Field\FormatterPluginManager $field_formatter_manager */
    $field_formatter_manager = \Drupal::service('plugin.manager.field.formatter');
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
    $field_manager = \Drupal::service('entity_field.manager');
    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $field_definitions */
    $field_definitions = $field_manager->getFieldDefinitions($this->element->getParent()->getParent()->getValue()['targetEntityType'], $this->element->getParent()->getParent()->getValue()['bundle']);

    // Let the layouts be named too.
    foreach ($parent_build['sections'] as $section_index => $value) {
      if (is_array($value) && !str_contains($parent_build['sections'][$section_index]['layout_settings']['#title'], '(Empty) Layout settings')) {
        $parent_build['sections'][$section_index]['#title'] = str_replace('Layout settings', '', $parent_build['sections'][$section_index]['layout_settings']['#title']);
      }
    }

    foreach ($element_names as $component_name) {
      // Not considering the layout builder case.
      [$section_index, $component_id, $component_name] = explode(PluginBase::DERIVATIVE_SEPARATOR, $component_name, 3);
      // phpcs:ignore DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable
      $item = &$parent_build['sections'][$section_index]['components'][$component_id]['configuration']['formatter'];
      $component = &$parent_build['sections'][$section_index]['components'][$component_id];

      /** @var \Drupal\Core\Field\FieldDefinitionInterface $definition */
      $definition = $field_definitions[$component_name] ?? NULL;
      if ($definition) {
        $field_type = $field_type_manager->getDefinition($definition->getType());

        $item['#title'] = $definition->getLabel();
        $component['#title'] = $definition->getLabel();
        $item['#description'] = t("Field: %name, type: @type", [
          '%name' => $component_name,
          '@type' => $field_type['label'],
        ]);
        // Set open state to let user reach settings without additional clicks.
        if (isset($item['#open'])) {
          $item['#open'] = TRUE;
        }

        $component_type = $field_type['id'];
        if (isset($item['settings']['#open'])) {
          $item['settings']['#open'] = TRUE;
        }

        /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $entity */
        // Set formatter type name if available.
        $formatter_options = $field_formatter_manager->getOptions($definition->getType());
        if (isset($formatter_options[$component_type]) && isset($item['settings'])) {
          $item['settings']['#title'] = t("%label format settings", ['%label' => $formatter_options[$component_type]]);
        }

      }
    }

  }

  /**
   * Set element names.
   */
  public static function setElementNames($data): array {
    return self::$data = $data;
  }

  /**
   * Get element names.
   */
  public static function getElementNames(): ?array {
    return self::$data;
  }

}
