<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a slider for input of a number within a specific range.
 *
 * Provides an HTML5 input element with type of "range".
 *
 * Properties:
 * - #min: Minimum value (defaults to 0).
 * - #max: Maximum value (defaults to 100).
 * Refer to \Drupal\Core\Render\Element\Number for additional properties.
 *
 * Usage example:
 * @code
 * $form['quantity'] = [
 *   '#type' => 'range',
 *   '#title' => $this->t('Quantity'),
 * ];
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Number
 */
#[FormElement('range')]
class Range extends Number {


  /**
   * @var mixed Automatically converted property
   */
  public $min;

  /**
   * @var mixed Automatically converted property
   */
  public $max;

  /**
   * @var mixed Automatically converted property
   */
  public $step;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    return [
      '#min' => 0,
      '#max' => 100,
      '#pre_render' => [
        [static::class, 'preRenderRange'],
      ],
      '#theme' => 'input__range',
    ] + $info;
  }

  /**
   * Prepares a #type 'range' render element for input.html.twig.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #title, #value, #description, #min, #max, #attributes,
   *   #step.
   *
   * @return array
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function preRenderRange($element) {
    $element['#attributes']['type'] = 'range';
    Element::setAttributes($element, ['id', 'name', 'value', 'step', 'min', 'max']);
    static::setAttributes($element, ['form-range']);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input === '') {
      $offset = ($element['#max'] - $element['#min']) / 2;

      // Round to the step.
      if (strtolower($element['#step']) != 'any') {
        $steps = round($offset / $element['#step']);
        $offset = $element['#step'] * $steps;
      }

      return $element['#min'] + $offset;
    }
  }

  /**
   * Create a range element.
   *
   * @param mixed $min
   *   Automatically converted param.
   * @param mixed $max
   *   Automatically converted param.
   * @param mixed $pre_render
   *   Automatically converted param.
   * @param mixed $theme
   *   Automatically converted param.
   * @param mixed $input
   *   Automatically converted param.
   * @param mixed $step
   *   Automatically converted param.
   * @param mixed $process
   *   Automatically converted param.
   * @param mixed $element_validate
   *   Automatically converted param.
   * @param mixed $theme_wrappers
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
  public static function createInstance($min, $max, $pre_render, $theme, $input, $step, $process, $element_validate, $theme_wrappers, $value_callback, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) : static {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('range');
    $instance->min = $min;
    $instance->max = $max;
    $instance->pre_render = $pre_render;
    $instance->theme = $theme;
    $instance->input = $input;
    $instance->step = $step;
    $instance->process = $process;
    $instance->element_validate = $element_validate;
    $instance->theme_wrappers = $theme_wrappers;
    $instance->value_callback = $value_callback;
    $instance->type = $type;
    $instance->defaults_loaded = $defaults_loaded;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderArray() : array {
    return [
      '#min' => $this->min,
      '#max' => $this->max,
      '#pre_render' => $this->pre_render,
      '#theme' => $this->theme,
      '#input' => $this->input,
      '#step' => $this->step,
      '#process' => $this->process,
      '#element_validate' => $this->element_validate,
      '#theme_wrappers' => $this->theme_wrappers,
      '#value_callback' => $this->value_callback,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
