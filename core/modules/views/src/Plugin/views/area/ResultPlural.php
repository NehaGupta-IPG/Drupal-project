<?php

namespace Drupal\views\Plugin\views\area;

use Drupal\Component\Gettext\PoItem;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\DefaultSummary;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;

/**
 * Views area handler to display result summary handling plural.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("result_plural")
 */
class ResultPlural extends Result {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['content_plural'] = [
      'default' => $this->t('Displaying @start - @end of @total'),
    ];
    $options['plural_count_token'] = ['default' => '@total'];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $format_plural_count_options = [
      '@start',
      '@end',
      '@total',
      '@label',
      '@per_page',
      '@current_page',
      '@current_record_count',
      '@page_count',
    ];
    $description = $form['content']['#description'];

    // Overrides parent plugin form.
    unset($form['content']['#description']);
    $form['content']['#title'] = $this->t('Singular form');
    $form['content']['#weight'] = 5;

    $form['plural_count_token'] = [
      '#type' => 'select',
      '#title' => $this->t('Count token'),
      '#description' => $this->t('Token used to detect plurality. If the token value is more than one, the "Plural form" textarea will be used.'),
      '#default_value' => ($this->options['plural_count_token'] ?? ''),
      '#options' => array_combine($format_plural_count_options, $format_plural_count_options),
    ];
    $form['content_plural'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Plural form'),
      '#description' => $description,
      '#default_value' => $this->options['content_plural'],
      '#weight' => 10,
      '#rows' => 3,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    // Must have options and does not work on summaries.
    if (!isset($this->options['content']) || !isset($this->options['content_plural']) || !isset($this->options['plural_count_token']) || $this->view->style_plugin instanceof DefaultSummary) {
      return [];
    }
    $output = '';
    $this->calculateTotalAndReplacements();
    $format = PluralTranslatableMarkup::createFromTranslatedString(
      $this->replacements[$this->options['plural_count_token']],
      implode(
        PoItem::DELIMITER,
        [
          $this->options['content'],
          $this->options['content_plural'],
        ]
      )
    );

    // Send the output.
    if (!empty($this->total) || !empty($this->options['empty'])) {
      $output .= Xss::filterAdmin(str_replace(array_keys($this->replacements), array_values($this->replacements), $format));
      // Return as render array.
      return [
        '#markup' => $output,
      ];
    }

    return [];
  }

}
