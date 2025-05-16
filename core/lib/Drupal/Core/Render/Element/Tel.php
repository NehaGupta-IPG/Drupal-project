<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a form element for entering a telephone number.
 *
 * Provides an HTML5 input element with type of "tel". It provides no special
 * validation.
 *
 * Properties:
 * - #size: The size of the input element in characters.
 * - #pattern: A string for the native HTML5 pattern attribute.
 *
 * Usage example:
 * @code
 * $form['phone'] = [
 *   '#type' => 'tel',
 *   '#title' => $this->t('Phone'),
 *   '#pattern' => '[^\d]*',
 * ];
 * @endcode
 *
 * @see \Drupal\Core\Render\Element
 */
#[FormElement('tel')]
class Tel extends FormElementBase {

  use ProcessPatternTrait;
  use ProcessAutocompleteTrait;

  /**
   * @var mixed Automatically converted property
   */
  public $size;

  /**
   * @var mixed Automatically converted property
   */
  public $maxlength;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#size' => 30,
      '#maxlength' => 128,
      '#autocomplete_route_name' => FALSE,
      '#process' => [
        [static::class, 'processAutocomplete'],
        [static::class, 'processAjaxForm'],
        [static::class, 'processPattern'],
      ],
      '#pre_render' => [
        [static::class, 'preRenderTel'],
      ],
      '#theme' => 'input__tel',
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Prepares a #type 'tel' render element for input.html.twig.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #title, #value, #description, #size, #maxlength,
   *   #placeholder, #required, #attributes.
   *
   * @return array
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function preRenderTel($element) {
    $element['#attributes']['type'] = 'tel';
    Element::setAttributes($element, ['id', 'name', 'value', 'size', 'maxlength', 'placeholder']);
    static::setAttributes($element, ['form-tel']);

    return $element;
  }

  public static function createInstance($input, $size, $maxlength, $autocomplete_route_name, $process, $pre_render, $theme, $theme_wrappers, $value_callback, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) {
    if (!$elementInfoManager) {
      $elementInfoManager = \Drupal::service('element_info');
    }
    $instance = $elementInfoManager->createInstance('tel');
    $instance->input = $input;
    $instance->size = $size;
    $instance->maxlength = $maxlength;
    $instance->autocomplete_route_name = $autocomplete_route_name;
    $instance->process = $process;
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
      '#size' => $this->size,
      '#maxlength' => $this->maxlength,
      '#autocomplete_route_name' => $this->autocomplete_route_name,
      '#process' => $this->process,
      '#pre_render' => $this->pre_render,
      '#theme' => $this->theme,
      '#theme_wrappers' => $this->theme_wrappers,
      '#value_callback' => $this->value_callback,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
