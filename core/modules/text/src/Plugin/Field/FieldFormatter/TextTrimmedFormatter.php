<?php

namespace Drupal\text\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
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

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, protected ElementInfoManagerInterface $elementInfo) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'trim_length' => '600',
      'exclude_html_tags' => TRUE,
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
    $element['exclude_html_tags'] = [
      '#title' => $this->t('Exclude HTML tags from trim length'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('exclude_html_tags'),
      '#description' => $this->t('If checked, HTML tags will not count toward the character limit.'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Trimmed limit: @trim_length characters', ['@trim_length' => $this->getSetting('trim_length')]);
    $summary[] = $this->t('Exclude HTML: @exclude_html_tags', [
      '@exclude_html_tags' => $this->getSetting('exclude_html_tags') ? $this->t('Yes') : $this->t('No'),
    ]);
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
      $element += $this->elementInfo->getInfo($element['#type']);
      // Add the #pre_render callback that renders the text into a summary.
      $element['#pre_render'][] = [TextTrimmedFormatter::class, 'preRenderSummary'];
      // Pass on the trim length to the #pre_render callback via a property.
      $element['#text_summary_trim_length'] = $this->getSetting('trim_length');
    };

    // The ProcessedText element already handles cache context & tag bubbling.
    // @see \Drupal\filter\Element\ProcessedText::preRenderText()
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'processed_text',
        '#text' => NULL,
        '#format' => $item->format,
        '#langcode' => $item->getLangcode(),
        '#exclude_html_tags' => $this->getSetting('exclude_html_tags'),
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
   *   - #exclude_html_tags: (optional) whether HTML tags should be excluded
   *     from the trim length count. Defaults to FALSE.
   *
   * @return array
   *   The passed-in element with the filtered text in '#markup' trimmed.
   *
   * @see filter_pre_render_text()
   * @see \Drupal\text\TextSummary::generate()
   */
  public static function preRenderSummary(array $element) {
    $raw_text = $element['#markup'];
    $exclude_html = $element['#exclude_html_tags'] ?? FALSE;
    $summary_service = \Drupal::service(TextSummary::class);

    // When a <!--break--> delimiter is present, preserve that by handing over
    // the raw text so the comment is not lost to strip_tags().
    if (!$exclude_html || str_contains($raw_text, '<!--break-->')) {
      $element['#markup'] = $summary_service->generate($raw_text, $element['#format'], $element['#text_summary_trim_length']);
      return $element;
    }

    $trimmed_plain_text = $summary_service->generate(strip_tags($raw_text), $element['#format'], $element['#text_summary_trim_length']);
    $element['#markup'] = self::truncateHtml($raw_text, strlen($trimmed_plain_text));

    return $element;
  }

  /**
   * Truncates an HTML string to a given length while preserving tags.
   *
   * @param string $html
   *   The HTML string to truncate.
   * @param int $length
   *   The desired character length.
   *
   * @return string
   *   The truncated HTML string.
   */
  public static function truncateHtml(string $html, int $length): string {
    $visible = 0;
    $offset = 0;
    $in_tag = FALSE;
    $total = strlen($html);
    while ($offset < $total && $visible < $length) {
      $char = $html[$offset];
      if ($char === '<') {
        $in_tag = TRUE;
      }
      elseif ($char === '>') {
        $in_tag = FALSE;
      }
      elseif (!$in_tag) {
        $visible++;
      }
      $offset++;
    }
    // Nothing was changed skip the normalization.
    if ($offset >= $total) {
      return $html;
    }
    // The fragment may end inside unclosed elements; normalize() repairs it.
    return Html::normalize(substr($html, 0, $offset));
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderSummary'];
  }

}
