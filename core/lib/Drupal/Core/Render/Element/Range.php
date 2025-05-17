<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;

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
   * Automatically converted property.
   *
   * @var mixed
   */
  public $min;

  /**
   * Automatically converted property.
   *
   * @var mixed
   */
  public $max;

  /**
   * Automatically converted property.
   *
   * @var mixed
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
