<?php

namespace Drupal\config_translation\FormElement;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Language\LanguageInterface;

/**
 * Defines the textfield element for the configuration translation interface.
 */
class Textfield extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getTranslationElement(LanguageInterface $translation_language, $source_config, $translation_config) {
    return NestedArray::mergeDeep(parent::getTranslationElement($translation_language, $source_config, $translation_config), [
      '#type' => 'textfield',
      '#attributes' => ['class' => ['js-text-full', 'text-full']],
    ]);
  }

}
