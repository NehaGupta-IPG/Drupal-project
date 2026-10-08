<?php

namespace Drupal\views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsFilter;

/**
 * Filter to handle dates stored as a timestamp.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter("date")]
class Date extends NumericFilter {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Value is already set up properly, we're just adding our new field to it.
    $options['value']['contains']['type']['default'] = 'date';

    $options['expose']['contains']['min_label'] = ['default' => $this->t('Start date')];
    $options['expose']['contains']['max_label'] = ['default' => $this->t('End date')];
    $options['end_date_inclusive'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $states = [];
    foreach ($this->operatorValues(2) as $operator) {
      $states[] = [':input[name="options[operator]"]' => ['value' => $operator]];
    }
    $form['end_date_inclusive'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include the whole end date'),
      '#description' => $this->t('When the end date of a range is a date without a time, such as 2026-10-06, include the whole of that day (up to 23:59:59) instead of stopping at its start (00:00:00).'),
      '#default_value' => !empty($this->options['end_date_inclusive']),
      '#states' => [
        'visible' => $states,
      ],
    ];
    // Grouped filters may use a range operator in any of their items.
    if ($this->isAGroup()) {
      unset($form['end_date_inclusive']['#states']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultExposeOptions(): void {
    parent::defaultExposeOptions();
    $this->options['expose']['min_label'] = $this->t('Start date');
    $this->options['expose']['max_label'] = $this->t('End date');
  }

  /**
   * Add a type selector to the value form.
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    if (!$form_state->get('exposed')) {
      $form['value']['type'] = [
        '#type' => 'radios',
        '#title' => $this->t('Value type'),
        '#options' => [
          'date' => $this->t('A date in any machine readable format. CCYY-MM-DD HH:MM:SS is preferred.'),
          'offset' => $this->t('An offset from the current time such as "@example1" or "@example2"', [
            '@example1' => '+1 day',
            '@example2' => '-2 hours -30 minutes',
          ]),
        ],
        '#default_value' => !empty($this->value['type']) ? $this->value['type'] : 'date',
      ];
    }
    parent::valueForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    if (!empty($this->options['exposed']) && $form_state->isValueEmpty(['options', 'expose', 'required'])) {
      // Who cares what the value is if it's exposed and non-required.
      return;
    }

    $this->validateValidTime($form['value'],
      $form_state,
      $form_state->getValue([
        'options',
        'operator',
      ]),
      $form_state->getValue(['options', 'value']));
  }

  /**
   * {@inheritdoc}
   */
  public function validateExposed(&$form, FormStateInterface $form_state) {
    if (empty($this->options['exposed'])) {
      return;
    }

    if (empty($this->options['expose']['required'])) {
      // Who cares what the value is if it's exposed and non-required.
      return;
    }

    $value = &$form_state->getValue($this->options['expose']['identifier']);
    if (!empty($this->options['expose']['use_operator']) && !empty($this->options['expose']['operator_id'])) {
      $operator = &$form_state->getValue($this->options['expose']['operator_id']);
    }
    else {
      $operator = $this->operator;
    }

    $this->validateValidTime($this->options['expose']['identifier'], $form_state, $operator, $value);

  }

  /**
   * Validate that the time values convert to something usable.
   */
  public function validateValidTime(&$form, FormStateInterface $form_state, $operator, $value) {
    $operators = $this->operators();

    if ($operators[$operator]['values'] == 1) {
      $convert = strtotime($value['value']);
      if (!empty($form['value']) && ($convert == -1 || $convert === FALSE)) {
        $form_state->setError($form['value'], $this->t('Invalid date format.'));
      }
    }
    elseif ($operators[$operator]['values'] == 2) {
      $min = strtotime($value['min']);
      if ($min == -1 || $min === FALSE) {
        $form_state->setError($form['min'], $this->t('Invalid date format.'));
      }
      $max = strtotime($value['max']);
      if ($max == -1 || $max === FALSE) {
        $form_state->setError($form['max'], $this->t('Invalid date format.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function hasValidGroupedValue(array $group) {
    if (!is_array($group['value']) || empty($group['value'])) {
      return FALSE;
    }

    // Special case when validating grouped date filters because the
    // $group['value'] array contains the type of filter (date or offset) and
    // therefore the number of items the comparison has to be done against is
    // one greater.
    $operators = $this->operators();
    $expected = $operators[$group['operator']]['values'] + 1;
    $actual = count(array_filter($group['value'], [static::class, 'arrayFilterZero']));

    return $actual == $expected;
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }

    // Store this because it will get overwritten.
    $type = NULL;
    if ($this->isAGroup()) {
      if (is_array($this->group_info)) {
        $type = $this->group_info['type'];
      }
    }
    else {
      $type = $this->value['type'];
    }
    $rc = parent::acceptExposedInput($input);

    // Restore what got overwritten by the parent.
    if (!is_null($type)) {
      $this->value['type'] = $type;
    }

    // Don't filter if value(s) are empty.
    $operators = $this->operators();
    if (!empty($this->options['expose']['use_operator']) && !empty($this->options['expose']['operator_id'])) {
      $operator = $input[$this->options['expose']['operator_id']];
    }
    else {
      $operator = $this->operator;
    }

    if ($operators[$operator]['values'] == 1) {
      // When the operator is either <, <=, =, !=, >=, > or regular_expression
      // the input contains only one value.
      if ($this->value['value'] == '') {
        // If the filter was not submitted but has a default offset value,
        // apply it so backends receive a resolved date.
        if (!empty($this->options['value']['value']) && $this->options['value']['type'] === 'offset') {
          $this->value['value'] = $this->options['value']['value'];
          $this->value['type'] = 'offset';
          return TRUE;
        }
        return FALSE;
      }
    }
    elseif ($operators[$operator]['values'] == 2) {
      // When the operator is either between or not between the input contains
      // two values.
      if ($this->value['min'] == '' || $this->value['max'] == '') {
        // If the filter was not submitted but has default offset values,
        // apply them so backends receive resolved dates.
        if ((!empty($this->options['value']['min']) || !empty($this->options['value']['max'])) && $this->options['value']['type'] === 'offset') {
          $this->value['min'] = $this->options['value']['min'];
          $this->value['max'] = $this->options['value']['max'];
          $this->value['type'] = 'offset';
          return TRUE;
        }
        return FALSE;
      }
    }

    return $rc;
  }

  /**
   * {@inheritdoc}
   */
  protected function opBetween($field) {
    $a = intval(strtotime($this->value['min'], 0));
    $b = intval(strtotime($this->getRangeMaxValue(), 0));

    if ($this->value['type'] == 'offset') {
      // Keep sign.
      $a = '***CURRENT_TIME***' . sprintf('%+d', $a);
      // Keep sign.
      $b = '***CURRENT_TIME***' . sprintf('%+d', $b);
    }
    // This is safe because we are manually scrubbing the values. It is
    // necessary to do it this way because $a and $b are formulas when using an
    // offset.
    $operator = strtoupper($this->operator);
    $this->query->addWhereExpression($this->options['group'], "$field $operator $a AND $b");
  }

  /**
   * Gets the maximum value of a range, extended to the end of the day.
   *
   * @return string
   *   The 'max' value. When the 'end_date_inclusive' option is enabled and the
   *   value is a date without a time, the time 23:59:59 is appended so that
   *   the whole end date is included in the range.
   */
  protected function getRangeMaxValue(): string {
    $max = trim((string) ($this->value['max'] ?? ''));
    if (!empty($this->options['end_date_inclusive']) && ($this->value['type'] ?? 'date') !== 'offset' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $max)) {
      return $max . ' 23:59:59';
    }
    return $max;
  }

  /**
   * {@inheritdoc}
   */
  protected function opSimple($field) {
    $value = intval(strtotime($this->value['value'], 0));
    if (!empty($this->value['type']) && $this->value['type'] == 'offset') {
      // Keep sign.
      $value = '***CURRENT_TIME***' . sprintf('%+d', $value);
    }
    // This is safe because we are manually scrubbing the value. It is necessary
    // to do it this way because $value is a formula when using an offset.
    $this->query->addWhereExpression($this->options['group'], "$field $this->operator $value");
  }

}
