<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides an HTML5 input element with type of "search".
 *
 * Usage example:
 * @code
 * $form['search'] = [
 *   '#type' => 'search',
 *   '#title' => $this->t('Search'),
 * ];
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Textfield
 */
#[FormElement('search')]
class Search extends FormElementBase {

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
      '#size' => 60,
      '#maxlength' => 128,
      '#autocomplete_route_name' => FALSE,
      '#process' => [
        [static::class, 'processAutocomplete'],
        [static::class, 'processAjaxForm'],
      ],
      '#pre_render' => [
        [static::class, 'preRenderSearch'],
      ],
      '#theme' => 'input__search',
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Prepares a #type 'search' render element for input.html.twig.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #title, #value, #description, #size, #maxlength,
   *   #placeholder, #required, #attributes.
   *
   * @return array
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function preRenderSearch($element) {
    $element['#attributes']['type'] = 'search';
    Element::setAttributes($element, ['id', 'name', 'value', 'size', 'maxlength', 'placeholder']);
    static::setAttributes($element, ['form-search']);

    return $element;
  }

  public static function createInstance($input, $size, $maxlength, $autocomplete_route_name, $process, $pre_render, $theme, $theme_wrappers, $value_callback, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('search');
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
