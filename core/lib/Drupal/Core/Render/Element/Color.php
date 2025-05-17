<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Component\Utility\Color as ColorUtility;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a form element for choosing a color.
 *
 * Properties:
 * - #default_value: Default value, in a format like #ffffff.
 *
 * Example usage:
 * @code
 * $form['color'] = [
 *   '#type' => 'color',
 *   '#title' => $this->t('Color'),
 *   '#default_value' => '#ffffff',
 * ];
 * @endcode
 */
#[FormElement('color')]
class Color extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#process' => [
        [static::class, 'processAjaxForm'],
      ],
      '#element_validate' => [
        [static::class, 'validateColor'],
      ],
      '#pre_render' => [
        [static::class, 'preRenderColor'],
      ],
      '#theme' => 'input__color',
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Form element validation handler for #type 'color'.
   */
  public static function validateColor(&$element, FormStateInterface $form_state, &$complete_form) {
    $value = trim($element['#value']);

    // Default to black if no value is given.
    // @see https://www.w3.org/TR/html5/number-state.html#color-state
    if ($value === '') {
      $form_state->setValueForElement($element, '#000000');
    }
    else {
      // Try to parse the value and normalize it.
      try {
        $form_state->setValueForElement($element, ColorUtility::rgbToHex(ColorUtility::hexToRgb($value)));
      }
      catch (\InvalidArgumentException) {
        $form_state->setError($element, t('%name must be a valid color.', ['%name' => empty($element['#title']) ? $element['#parents'][0] : $element['#title']]));
      }
    }
  }

  /**
   * Prepares a #type 'color' render element for input.html.twig.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #title, #value, #description, #attributes.
   *
   * @return array
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function preRenderColor($element) {
    $element['#attributes']['type'] = 'color';
    Element::setAttributes($element, ['id', 'name', 'value']);
    static::setAttributes($element, ['form-color']);

    return $element;
  }

  public static function createInstance($input, $process, $element_validate, $pre_render, $theme, $theme_wrappers, $value_callback, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('color');
    $instance->input = $input;
    $instance->process = $process;
    $instance->element_validate = $element_validate;
    $instance->pre_render = $pre_render;
    $instance->theme = $theme;
    $instance->theme_wrappers = $theme_wrappers;
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
      '#process' => $this->process,
      '#element_validate' => $this->element_validate,
      '#pre_render' => $this->pre_render,
      '#theme' => $this->theme,
      '#theme_wrappers' => $this->theme_wrappers,
      '#value_callback' => $this->value_callback,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
