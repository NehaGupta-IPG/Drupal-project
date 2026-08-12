<?php

namespace Drupal\text\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\text\TextSummary;

/**
 * Plugin implementation of the 'text_trimmed' formatter.
 *
 * Note: This class also contains the implementations used by the
 * 'text_summary_or_trimmed' formatter.
 *
 * @see \Drupal\text\Field\Formatter\TextSummaryOrTrimmedFormatter
 */
#[FieldFormatter(
  id: 'text_trimmed',
  label: new TranslatableMarkup('Trimmed'),
  field_types: [
    'text',
    'text_long',
    'text_with_summary',
  ],
)]
class TextTrimmedFormatter extends FormatterBase implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'trim_length' => '600',
      'add_ellipsis' => FALSE,
      'min_wordsafe_length' => 1,
      'wordsafe' => FALSE,
      'breakpoints' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['trim_length'] = [
      '#title' => $this->t('Trimmed limit'),
      '#type' => 'number',
      '#field_suffix' => $this->t('characters'),
      '#default_value' => $this->getSetting('trim_length'),
      '#description' => $this->t('If the summary is not set, the trimmed %label field will end at the last full sentence before this character limit.', ['%label' => $this->fieldDefinition->getLabel()]),
      '#min' => 1,
      '#required' => TRUE,
    ];
    $element['add_ellipsis'] = [
      '#title' => $this->t('Add ellipsis'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('add_ellipsis'),
      '#description' => $this->t('Displays an ellipsis after trimmed text.'),
    ];
    $field_name = $this->fieldDefinition->getName();
    $field_breakpoints_selector = "fields[$field_name][settings_edit_form][settings][breakpoints]";
    $element['wordsafe'] = [
      '#title' => $this->t('Wordsafe'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('wordsafe'),
      '#description' => $this->t('Trim on word boundaries.'),
      // The "breakpoints" trim already include word boundary as it is a trim
      // on "end of" paragraph, line or sentence.
      '#states' => [
        'checked' => [
          'input[name="' . $field_breakpoints_selector . '"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $field_wordsafe_selector = "fields[$field_name][settings_edit_form][settings][wordsafe]";
    $element['min_wordsafe_length'] = [
      '#title' => $this->t('Minimum acceptable length for truncation'),
      '#type' => 'number',
      '#field_suffix' => $this->t('characters'),
      '#default_value' => $this->getSetting('min_wordsafe_length'),
      '#min' => 1,
      '#description' => $this->t('The minimum acceptable length for truncation, if wordsafe is TRUE.'),
      '#states' => [
        'invisible' => [
          'input[name="' . $field_wordsafe_selector . '"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $element['breakpoints'] = [
      '#title' => $this->t('Use breakpoints'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('breakpoints'),
      '#description' => $this->t('Trim on breakpoints like end of paragraph, line break or end of a sentence ("&lt;/p&gt;", "&lt;br&gt;", ".", "!", "...").'),
      // The "breakpoints" trim has lower granularity than the "wordsafe".
      // If the later is chosen for higher granularity then there is no sense
      // to have it.
      '#states' => [
        'checked' => [
          'input[name="' . $field_wordsafe_selector . '"]' => ['checked' => FALSE],
        ],
      ],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Trimmed limit: @trim_length characters', ['@trim_length' => $this->getSetting('trim_length')]);
    if ($this->getSetting('wordsafe')) {
      $summary[] = $this->t(
        'Truncating on a word boundary, with a minimum of @length characters',
        ['@length' => $this->getSetting('min_wordsafe_length')]
      );
    }
    if ($this->getSetting('breakpoints')) {
      $summary[] = $this->t('Truncating on breakpoints as end of paragraph, line break or end of a sentence');
    }
    if ($this->getSetting('add_ellipsis')) {
      $summary[] = $this->t('Ellipsis add to the end of the truncated string');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $render_as_summary = function (&$element) {
      // Make sure any default #pre_render callbacks are set on the element,
      // because text_pre_render_summary() must run last.
      $element += \Drupal::service('element_info')->getInfo($element['#type']);
      // Add the #pre_render callback that renders the text into a summary.
      $element['#pre_render'][] = [TextTrimmedFormatter::class, 'preRenderSummary'];
      // Pass on default settings to the #pre_render callback via a property.
      $element['#text_summary_trim_length'] = $this->getSetting('trim_length');
      $element['#text_summary_wordsafe'] = $this->getSetting('wordsafe');
      $element['#text_summary_breakpoints'] = $this->getSetting('breakpoints');
      $element['#text_summary_add_ellipsis'] = $this->getSetting('add_ellipsis');
      $element['#text_summary_min_wordsafe_length'] = $this->getSetting('min_wordsafe_length');
    };

    // The ProcessedText element already handles cache context & tag bubbling.
    // @see \Drupal\filter\Element\ProcessedText::preRenderText()
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'processed_text',
        '#text' => NULL,
        '#format' => $item->format,
        '#langcode' => $item->getLangcode(),
      ];

      if ($this->getPluginId() == 'text_summary_or_trimmed' && !empty($item->summary)) {
        $elements[$delta]['#text'] = $item->summary;
      }
      else {
        $elements[$delta]['#text'] = $item->value;
        $render_as_summary($elements[$delta]);
      }
    }

    return $elements;
  }

  /**
   * Pre-render callback: Renders a processed text element's #markup summary.
   *
   * @param array $element
   *   A structured array with the following key-value pairs:
   *   - #markup: the filtered text (as filtered by filter_pre_render_text())
   *   - #format: containing the machine name of the filter format to be used to
   *     filter the text. Defaults to the fallback format. See
   *     filter_fallback_format().
   *   - #text_summary_trim_length: the desired character length of the summary
   *     (used by \Drupal\text\TextSummary::generate())
   *    - #text_summary_wordsafe: If TRUE, attempt to truncate on a word
   *      boundary.
   *      (used by \Drupal\text\TextSummary::generate())
   *    - #text_summary_breakpoints: If TRUE, attempt to truncate on a
   *      breakpoints as end of paragraph, line break or end of a sentence.
   *      (used by \Drupal\text\TextSummary::generate())
   *    - #text_summary_add_ellipsis: If TRUE, add '...' to the end of the
   *      truncated string.
   *      (used by \Drupal\text\TextSummary::generate())
   *    - #text_summary_min_wordsafe_length: the minimum acceptable length
   *      for truncation
   *      (used by \Drupal\text\TextSummary::generate())
   *
   * @return array
   *   The passed-in element with the filtered text in '#markup' trimmed.
   *
   * @see filter_pre_render_text()
   * @see \Drupal\text\TextSummary::generate()
   */
  public static function preRenderSummary(array $element) {
    $element['#markup'] = \Drupal::service(TextSummary::class)->generate(
      $element['#markup'],
      $element['#format'],
      $element['#text_summary_trim_length'],
      $element['#text_summary_wordsafe'],
      $element['#text_summary_breakpoints'],
      $element['#text_summary_add_ellipsis'],
      $element['#text_summary_min_wordsafe_length']
    );
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderSummary'];
  }

}
