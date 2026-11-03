<?php

namespace Drupal\layout_builder\FormElement;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\field_ui\FormElement\EntityDisplayElement;

/**
 * Removes duplicated fields from element build.
 */
class LayoutBuilderEntityDisplayElement extends EntityDisplayElement {

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

    if (isset($parent_build['third_party_settings']['layout_builder']['sections'])) {
      // Add open state for details with sections, but don't open each section.
      $parent_build['third_party_settings']['layout_builder']['#open'] = TRUE;
      $parent_build['third_party_settings']['layout_builder']['sections']['#open'] = TRUE;
      $sections = &$parent_build['third_party_settings']['layout_builder']['sections'];
      foreach ($sections as $weight => &$section) {
        if (!is_array($section) || (is_array($section) && !isset($section['components']))) {
          continue;
        }
        // Same state logic for details with components: keep each component
        // closed, but open all details inside it.
        $section['components']['#open'] = TRUE;
        foreach ($section['components'] as $uuid => &$component) {
          if (!is_array($component) || (is_array($component) && !isset($component['configuration']['formatter']))) {
            continue;
          }
          if (!isset($source_config['third_party_settings']['layout_builder']['sections'][$weight]['components'][$uuid])) {
            continue;
          }
          $component_config = $source_config['third_party_settings']['layout_builder']['sections'][$weight]['components'][$uuid];
          $field_name = '';
          try {
            [,,, $field_name] = explode(PluginBase::DERIVATIVE_SEPARATOR, $component_config['configuration']['id']);
          }
          catch (\Exception) {
            continue;
          }
          $component['configuration']['#open'] = TRUE;
          $component['configuration']['formatter']['#open'] = TRUE;
          $component['configuration']['formatter']['settings']['#open'] = TRUE;
          // Take existing labels from content section.
          if (!empty($parent_build['content'][$field_name]['#description'])) {
            $component['configuration']['formatter']['#title'] = $parent_build['content'][$field_name]['#title'];
            $component['configuration']['formatter']['#description'] = $parent_build['content'][$field_name]['#description'];
            $component['configuration']['formatter']['settings']['#title'] = $parent_build['content'][$field_name]['settings']['#title'];
          }
        }
      }
      // Don't provide duplicate settings for fields under layout builder.
      unset($parent_build['content']);
    }

    return $parent_build;
  }

}
