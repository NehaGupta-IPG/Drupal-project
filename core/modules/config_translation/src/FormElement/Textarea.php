<?php

namespace Drupal\config_translation\FormElement;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Language\LanguageInterface;

/**
 * Defines the textarea element for the configuration translation interface.
 */
class Textarea extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getTranslationElement(LanguageInterface $translation_language, $source_config, $translation_config) {
    // Estimate a comfortable size of the input textarea.
    if (is_string($translation_config)) {
      $rows_words = ceil(str_word_count($translation_config) / 5);
      $rows_newlines = substr_count($translation_config, "\n") + 1;
      $rows = max($rows_words, $rows_newlines);
    }

    return NestedArray::mergeDeep(parent::getTranslationElement($translation_language, $source_config, $translation_config), [
      '#type' => 'textarea',
      '#rows' => $rows ?? 1,
      '#attributes' => ['class' => ['js-text-full', 'text-full']],
    ]);
  }

}
