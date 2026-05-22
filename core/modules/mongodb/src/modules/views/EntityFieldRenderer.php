<?php

namespace Drupal\mongodb\modules\views;

use Drupal\views\Entity\Render\ConfigurableLanguageRenderer;
use Drupal\views\Entity\Render\EntityFieldRenderer as CoreEntityFieldRenderer;
use Drupal\views\Plugin\views\PluginBase;

/**
 * Overriding the views class \Drupal\views\Entity\Render\EntityFieldRenderer.
 */
class EntityFieldRenderer extends CoreEntityFieldRenderer {

  /**
   * {@inheritdoc}
   */
  protected function getEntityTranslationRenderer() {
    if (!isset($this->entityTranslationRenderer)) {
      $view = $this->getView();
      $rendering_language = $view->display_handler->getOption('rendering_language');
      $langcode = NULL;
      $dynamic_renderers = [
        '***LANGUAGE_entity_translation***' => 'TranslationLanguageRenderer',
        '***LANGUAGE_entity_default***' => 'DefaultLanguageRenderer',
      ];
      if (isset($dynamic_renderers[$rendering_language])) {
        // Dynamic language set based on result rows or instance defaults.
        $renderer = $dynamic_renderers[$rendering_language];
      }
      else {
        if (strpos($rendering_language, '***LANGUAGE_') !== FALSE) {
          $langcode = PluginBase::queryLanguageSubstitutions()[$rendering_language];
        }
        else {
          // Specific langcode set.
          $langcode = $rendering_language;
        }
        $renderer = 'ConfigurableLanguageRenderer';
      }

      // Get the entity type.
      $entity_type = $this->getEntityTypeManager()->getDefinition($this->getEntityTypeId());

      // Get the MongoDB version of the class
      // \Drupal\views\Entity\Render\TranslationLanguageRenderer.
      if ($renderer == 'TranslationLanguageRenderer') {
        $this->entityTranslationRenderer = new TranslationLanguageRenderer($view, $this->getLanguageManager(), $entity_type);
      }
      elseif ($renderer == 'ConfigurableLanguageRenderer') {
        $this->entityTranslationRenderer = new ConfigurableLanguageRenderer($view, $this->getLanguageManager(), $entity_type, $langcode);
      }
      else {
        $class = '\Drupal\views\Entity\Render\\' . $renderer;
        $this->entityTranslationRenderer = new $class($view, $this->getLanguageManager(), $entity_type);
      }
    }
    return $this->entityTranslationRenderer;
  }

}
