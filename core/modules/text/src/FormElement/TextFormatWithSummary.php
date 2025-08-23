<?php

namespace Drupal\text\FormElement;

use Drupal\config_translation\FormElement\TextFormat;
use Drupal\Core\Language\LanguageInterface;

/**
 * Provides a configuration translation form element for text with a summary.
 */
class TextFormatWithSummary extends TextFormat {

  /**
   * {@inheritdoc}
   */
  public function getTranslationElement(LanguageInterface $translation_language, $source_config, $translation_config) {
    $element = parent::getTranslationElement($translation_language, $source_config, $translation_config);

    // @see \Drupal\text\Plugin\Field\FieldWidget\TextareaWithSummaryWidget::formElement().
    $element['summary'] = [
      // The 'text_with_summary' field item allows to disable the summary.
      // However, there is no way to figure out whether the field to which this
      // form element belongs actually has the summary enabled, so we always
      // show a summary to be safe.
      '#type' => 'textarea',
      '#default_value' => $translation_config['summary'],
      '#title' => t('Summary'),
      '#description' => t('Leave blank to use trimmed value of full text as the summary.'),
      '#attached' => [
        'library' => ['text/drupal.text'],
      ],
      '#attributes' => ['class' => ['js-text-summary', 'text-summary']],
      '#prefix' => '<div class="js-text-summary-wrapper text-summary-wrapper">',
      '#suffix' => '</div>',
      '#weight' => -10,
    ];

    return $element;
  }

}
