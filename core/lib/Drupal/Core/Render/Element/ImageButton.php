<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a form element for a submit button with an image.
 */
#[FormElement('image_button')]
class ImageButton extends Submit {


  /**
   * @var mixed Automatically converted property
   */
  public $return_value;

  /**
   * @var mixed Automatically converted property
   */
  public $src;

  /**
   * @var mixed Automatically converted property
   */
  public $executes_submit_callback;

  /**
   * @var mixed Automatically converted property
   */
  public $submit_button;

  /**
   * @var mixed Automatically converted property
   */
  public $limit_validation_errors;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    unset($info['name']);

    return [
      '#return_value' => TRUE,
      '#has_garbage_value' => TRUE,
      '#src' => NULL,
      '#theme_wrappers' => ['input__image_button'],
    ] + $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE) {
      if (!empty($input)) {
        // If we're dealing with Mozilla or Opera, we're lucky. It will
        // return a proper value, and we can get on with things.
        return $element['#return_value'];
      }
      else {
        // Unfortunately, in IE we never get back a proper value for THIS
        // form element. Instead, we get back two split values: one for the
        // X and one for the Y coordinates on which the user clicked the
        // button. We'll find this element in the #post data, and search
        // in the same spot for its name, with '_x'.
        $input = $form_state->getUserInput();
        foreach (explode('[', $element['#name']) as $element_name) {
          // Chop off the ] that may exist.
          if (str_ends_with($element_name, ']')) {
            $element_name = substr($element_name, 0, -1);
          }

          if (!isset($input[$element_name])) {
            if (isset($input[$element_name . '_x'])) {
              return $element['#return_value'];
            }
            return NULL;
          }
          $input = $input[$element_name];
        }
        return $element['#return_value'];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preRenderButton($element) {
    $element['#attributes']['type'] = 'image';
    Element::setAttributes($element, ['id', 'name', 'value']);

    $element['#attributes']['src'] = \Drupal::service('file_url_generator')->generateString($element['#src']);
    if (!empty($element['#title'])) {
      $element['#attributes']['alt'] = $element['#title'];
      $element['#attributes']['title'] = $element['#title'];
    }

    $element['#attributes']['class'][] = 'image-button';
    if (!empty($element['#button_type'])) {
      $element['#attributes']['class'][] = 'image-button--' . $element['#button_type'];
    }
    $element['#attributes']['class'][] = 'js-form-submit';
    $element['#attributes']['class'][] = 'form-submit';

    if (!empty($element['#attributes']['disabled'])) {
      $element['#attributes']['class'][] = 'is-disabled';
    }

    return $element;
  }

  /**
   * Create an image_button element.
   *
   * @param mixed $return_value
   *   Automatically converted param.
   * @param mixed $has_garbage_value
   *   Automatically converted param.
   * @param mixed $src
   *   Automatically converted param.
   * @param mixed $theme_wrappers
   *   Automatically converted param.
   * @param mixed $executes_submit_callback
   *   Automatically converted param.
   * @param mixed $input
   *   Automatically converted param.
   * @param mixed $name
   *   Automatically converted param.
   * @param mixed $is_button
   *   Automatically converted param.
   * @param mixed $submit_button
   *   Automatically converted param.
   * @param mixed $limit_validation_errors
   *   Automatically converted param.
   * @param mixed $process
   *   Automatically converted param.
   * @param mixed $pre_render
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
  public static function createInstance($return_value, $has_garbage_value, $src, $theme_wrappers, $executes_submit_callback, $input, $name, $is_button, $submit_button, $limit_validation_errors, $process, $pre_render, $value_callback, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) : static {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('image_button');
    $instance->return_value = $return_value;
    $instance->has_garbage_value = $has_garbage_value;
    $instance->src = $src;
    $instance->theme_wrappers = $theme_wrappers;
    $instance->executes_submit_callback = $executes_submit_callback;
    $instance->input = $input;
    $instance->name = $name;
    $instance->is_button = $is_button;
    $instance->submit_button = $submit_button;
    $instance->limit_validation_errors = $limit_validation_errors;
    $instance->process = $process;
    $instance->pre_render = $pre_render;
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
      '#return_value' => $this->return_value,
      '#has_garbage_value' => $this->has_garbage_value,
      '#src' => $this->src,
      '#theme_wrappers' => $this->theme_wrappers,
      '#executes_submit_callback' => $this->executes_submit_callback,
      '#input' => $this->input,
      '#name' => $this->name,
      '#is_button' => $this->is_button,
      '#submit_button' => $this->submit_button,
      '#limit_validation_errors' => $this->limit_validation_errors,
      '#process' => $this->process,
      '#pre_render' => $this->pre_render,
      '#value_callback' => $this->value_callback,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
