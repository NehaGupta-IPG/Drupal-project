<?php

namespace Drupal\Core\Render\Element;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManager;
use Drupal\Core\Render\Element\ProcessAutocompleteTrait;
use Drupal\Core\Render\Element\ProcessPatternTrait;

/**
 * Provides a form element for input of a URL.
 *
 * Properties:
 * - #default_value: A valid URL string.
 * - #size: The size of the input element in characters.
 * - #pattern: A string for the native HTML5 pattern attribute.
 *
 * Usage example:
 * @code
 * $form['homepage'] = [
 *   '#type' => 'url',
 *   '#title' => $this->t('Home Page'),
 *   '#size' => 30,
 *   '#pattern' => '*.example.com',
 *   ...
 * ];
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Textfield
 */
#[FormElement('url')]
class Url extends FormElementBase {

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
      '#size' => 60,
      '#maxlength' => 255,
      '#autocomplete_route_name' => FALSE,
      '#process' => [
        [static::class, 'processAutocomplete'],
        [static::class, 'processAjaxForm'],
        [static::class, 'processPattern'],
      ],
      '#element_validate' => [
        [static::class, 'validateUrl'],
      ],
      '#pre_render' => [
        [static::class, 'preRenderUrl'],
      ],
      '#theme' => 'input__url',
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Form element validation handler for #type 'url'.
   *
   * Note that #maxlength and #required is validated by _form_validate()
   * already.
   */
  public static function validateUrl(&$element, FormStateInterface $form_state, &$complete_form) {
    $value = trim($element['#value']);
    $form_state->setValueForElement($element, $value);

    if ($value !== '' && !UrlHelper::isValid($value, TRUE)) {
      $form_state->setError($element, t('The URL %url is not valid.', ['%url' => $value]));
    }
  }

  /**
   * Prepares a #type 'url' render element for input.html.twig.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #title, #value, #description, #size, #maxlength,
   *   #placeholder, #required, #attributes.
   *
   * @return array
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function preRenderUrl($element) {
    $element['#attributes']['type'] = 'url';
    Element::setAttributes($element, ['id', 'name', 'value', 'size', 'maxlength', 'placeholder']);
    static::setAttributes($element, ['form-url']);

    return $element;
  }
  public static function createInstance($input, $size, $maxlength, $autocomplete_route_name, $process, $element_validate, $pre_render, $theme, $theme_wrappers, $value_callback, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) {
    if (!$elementInfoManager) {
      $elementInfoManager = \Drupal::service('element_info');
    }
    $instance = $elementInfoManager->createInstance('url');
    $instance->input = $input;
    $instance->size = $size;
    $instance->maxlength = $maxlength;
    $instance->autocomplete_route_name = $autocomplete_route_name;
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
      '#size' => $this->size,
      '#maxlength' => $this->maxlength,
      '#autocomplete_route_name' => $this->autocomplete_route_name,
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
