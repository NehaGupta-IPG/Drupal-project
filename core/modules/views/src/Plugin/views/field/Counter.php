<?php

namespace Drupal\views\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\ResultRow;

/**
 * Field handler to show a counter of the current row.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("counter")]
class Counter extends FieldPluginBase {

  use UncacheableFieldHandlerTrait;

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['counter_start'] = ['default' => 1];
    $options['reverse'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['counter_start'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Starting value'),
      '#default_value' => $this->options['counter_start'],
      '#description' => $this->t('Specify the number the counter should start at.'),
      '#size' => 2,
    ];

    $form['reverse'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reverse'),
      '#default_value' => $this->options['reverse'],
      '#description' => $this->t('Reverse the counter.'),
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL) {
    // The current row index in the view results.
    $row_index = $this->view->row_index;

    // The pager object used by the view.
    $pager = $this->view->pager;

    // Get the starting value from options, default to 1 if not numeric.
    $start = is_numeric($this->options['counter_start']) ? (int) $this->options['counter_start'] : 1;

    // Determine if reverse counting is enabled.
    $reverse = !empty($this->options['reverse']);

    // Calculate the number of items skipped due to pagination.
    // This represents the offset from previous pages.
    $offset = $pager->usePager()
      ? ($pager->getItemsPerPage() * $pager->getCurrentPage()) + $pager->getOffset()
      : 0;

    if ($reverse) {
      // Get total number of items from the pager if available.
      // Fallback to count of current view result if total_items is not set.
      $total = $pager->total_items ?? count($this->view->result);

      // Count down from total minus offset and current row index.
      return $total - $offset - $row_index;
    }

    // Standard counter: increment from starting value with pagination offset.
    return $start + $offset + $row_index;
  }

}
