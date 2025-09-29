<?php

namespace Drupal\views\Plugin\views\area;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsArea;
use Drupal\views\Plugin\views\style\DefaultSummary;

/**
 * Views area handler to display some configurable result summary.
 *
 * @ingroup views_area_handlers
 */
#[ViewsArea("result")]
class Result extends AreaPluginBase {

  /**
   * Total number of results.
   *
   * @var int
   */
  protected int $total;

  /**
   * The token replacements.
   *
   * @var array
   */
  protected array $replacements = [];

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['content'] = [
      'default' => $this->t('Displaying @start - @end of @total'),
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $item_list = [
      '#theme' => 'item_list',
      '#items' => [
        '@start -- ' . $this->t('The initial record number in the set.'),
        '@end -- ' . $this->t('The last record number in the set.'),
        '@total -- ' . $this->t('The total records in the set.'),
        '@format_total -- ' . $this->t('The total records in the set (number formatted)'),
        '@label -- ' . $this->t('The human-readable name of the view.'),
        '@per_page -- ' . $this->t('The number of items per page.'),
        '@current_page -- ' . $this->t('The current page number.'),
        '@current_record_count -- ' . $this->t('The current page record count.'),
        '@page_count -- ' . $this->t('The total page count.'),
      ],
    ];
    $list = \Drupal::service('renderer')->render($item_list);
    $form['content'] = [
      '#title' => $this->t('Display'),
      '#type' => 'textarea',
      '#rows' => 3,
      '#default_value' => $this->options['content'],
      '#description' => $this->t('You may use HTML code in this field. The following tokens are supported:') . $list,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (strpos($this->options['content'], '@total') !== FALSE || strpos($this->options['content'], '@format_total') !== FALSE) {
      $this->view->get_total_rows = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    // Must have options and does not work on summaries.
    if (!isset($this->options['content']) || $this->view->style_plugin instanceof DefaultSummary) {
      return [];
    }
    $output = '';
    $this->calculateTotalAndReplacements();
    $format = $this->options['content'];

    // Send the output.
    if (!empty($this->total) || !empty($this->options['empty'])) {
      $output .= str_replace(array_keys($this->replacements), array_values($this->replacements), $format);
      // Return as render array.
      return [
        '#markup' => $output,
      ];
    }

    return [];
  }

  /**
   * Calculate the replacement tokens.
   */
  protected function calculateTotalAndReplacements(): void {
    // Calculate the page totals.
    $current_page = (int) $this->view->getCurrentPage() + 1;
    $per_page = (int) $this->view->getItemsPerPage();
    // @todo Maybe use a possible is views empty functionality.
    // Not every view has total_rows set, use view->result instead.
    $this->total = $this->view->total_rows ?? count($this->view->result);
    $label = Html::escape($this->view->storage->label());
    // If there is no result the "start" and "current_record_count" should be
    // equal to 0. To have the same calculation logic, we use a "start offset"
    // to handle all the cases.
    $start_offset = empty($this->total) ? 0 : 1;
    if ($per_page === 0) {
      $page_count = 1;
      $start = $start_offset;
      $end = $this->total;
    }
    else {
      $page_count = (int) ceil($this->total / $per_page);
      $total_count = $current_page * $per_page;
      if ($total_count > $this->total) {
        $total_count = $this->total;
      }
      $start = ($current_page - 1) * $per_page + $start_offset;
      $end = $total_count;
    }
    $current_record_count = ($end - $start) + $start_offset;
    // Get the search information.
    $this->replacements['@start'] = $start;
    $this->replacements['@end'] = $end;
    $this->replacements['@total'] = $this->total;
    $this->replacements['@format_total'] = number_format($this->total, 0, '.', ',');
    $this->replacements['@label'] = $label;
    $this->replacements['@per_page'] = $per_page;
    $this->replacements['@current_page'] = $current_page;
    $this->replacements['@current_record_count'] = $current_record_count;
    $this->replacements['@page_count'] = $page_count;
  }

}
