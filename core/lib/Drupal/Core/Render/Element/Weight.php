<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a form element for input of a weight.
 *
 * Weights are integers used to indicate ordering, with larger numbers later in
 * the order.
 *
 * Properties:
 * - #delta: The range of possible weight values used. A delta of 10 would
 *   indicate possible weight values between -10 and 10.
 *
 * Usage example:
 * @code
 * $form['weight'] = [
 *   '#type' => 'weight',
 *   '#title' => $this->t('Weight'),
 *   '#default_value' => $edit['weight'],
 *   '#delta' => 10,
 * ];
 * @endcode
 */
#[FormElement('weight')]
class Weight extends FormElementBase {


  /**
   * @var mixed Automatically converted property
   */
  public $delta;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#delta' => 10,
      '#default_value' => 0,
      '#process' => [
        [static::class, 'processWeight'],
        [static::class, 'processAjaxForm'],
      ],
    ];
  }

  /**
   * Expands a weight element into a select/number element.
   */
  public static function processWeight(&$element, FormStateInterface $form_state, &$complete_form) {
    // If the number of options is small enough, use a select field. Otherwise,
    // use a number field.
    $type = $element['#delta'] <= \Drupal::config('system.site')->get('weight_select_max') ? 'select' : 'number';
    $element = array_merge($element, \Drupal::service('element_info')->getInfo($type));
    $element['#is_weight'] = TRUE;

    if ($type === 'select') {
      $weights = [];
      for ($n = (-1 * $element['#delta']); $n <= $element['#delta']; $n++) {
        $weights[$n] = $n;
      }
      $default_value = (int) $element['#default_value'];
      if (!isset($weights[$default_value])) {
        $weights[$default_value] = $default_value;
        ksort($weights);
      }
      $element['#options'] = $weights;
    }
    else {
      // Use a field big enough to fit most weights.
      $element['#size'] = 10;
    }

    return $element;
  }

  /**
   * Create a weight element.
   *
   * @param mixed $input
   *   Automatically converted param.
   * @param mixed $delta
   *   Automatically converted param.
   * @param mixed $default_value
   *   Automatically converted param.
   * @param mixed $process
   *   Automatically converted param.
   * @param mixed $value_callback
   *   Automatically converted param.
   * @param mixed $type
   *   Automatically converted param.
   * @param mixed $defaults_loaded
   *   Automatically converted param.
   * @param ?\Drupal\Core\Render\ElementInfoManager $elementInfoManager
   *   The element info manager.
   *
   * @return static
   */
  public static function createInstance($input, $delta, $default_value, $process, $value_callback, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) : static {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('weight');
    $instance->input = $input;
    $instance->delta = $delta;
    $instance->default_value = $default_value;
    $instance->process = $process;
    $instance->value_callback = $value_callback;
    $instance->type = $type;
    $instance->defaults_loaded = $defaults_loaded;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderArray() {
    return [
      '#input' => $this->input,
      '#delta' => $this->delta,
      '#default_value' => $this->default_value,
      '#process' => $this->process,
      '#value_callback' => $this->value_callback,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
