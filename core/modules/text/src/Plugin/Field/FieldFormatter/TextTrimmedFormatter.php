<?php

namespace Drupal\text\Plugin\Field\FieldFormatter;

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
   *
   * @return array
   *   The passed-in element with the filtered text in '#markup' trimmed.
   *
   * @see filter_pre_render_text()
   * @see \Drupal\text\TextSummary::generate()
   */
  public static function preRenderSummary(array $element) {
    $raw_text = $element['#markup'];
    $exclude_html = $element['#exclude_html_tags'] ?? TRUE;

    if ($exclude_html) {
      $plain_text = strip_tags($raw_text);
    }
    else {
      $plain_text = $raw_text;
    }
    $trimmed_plain_text = \Drupal::service(TextSummary::class)->generate($plain_text, $element['#format'], $element['#text_summary_trim_length']);

    if ($exclude_html) {
      $element['#markup'] = self::truncateHtml($raw_text, strlen($trimmed_plain_text));
    }
    else {
      $element['#markup'] = $trimmed_plain_text;
    }

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
    $is_open = FALSE;
    $ret = '';
    $i = 0;
    $tags = [];
    $tag = '';
    $stripped_text = strip_tags($html);

    // If the text is shorter than the required length, return it as is.
    if (strlen($stripped_text) <= $length) {
      return $html;
    }

    // Use a loop to walk through the string.
    while ($i < strlen($html) && strlen(strip_tags($ret)) < $length) {
      $char = $html[$i];
      $ret .= $char;

      // Handle tags.
      if ($char === '<') {
        $is_open = TRUE;
        $tag = '';
      }
      elseif ($is_open && $char === '>') {
        $is_open = FALSE;

        // Add open tags to the list and handle self-closing tags.
        if ($tag[0] != '/' && !str_ends_with($tag, '/')) {
          $tags[] = $tag;
        }
        elseif ($tag[0] == '/') {
          array_pop($tags);
        }
      }
      elseif ($is_open) {
        $tag .= $char;
      }
      $i++;
    }

    // Close any open tags.
    while (count($tags) > 0) {
      $ret .= '</' . array_pop($tags) . '>';
    }

    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderSummary'];
  }

}
