<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\ElementInfoManager;

/**
 * Provides a form submit button.
 *
 * Submit buttons are processed the same as regular buttons, except they trigger
 * the form's submit handler.
 *
 * Properties:
 * - #submit: Specifies an alternate callback for form submission when the
 *   submit button is pressed.  Use '::methodName' format or an array containing
 *   the object and method name (for example, [ $this, 'methodName'] ).
 * - #value: The text to be shown on the button.
 *
 * Usage Example:
 * @code
 * $form['actions']['submit'] = [
 *   '#type' => 'submit',
 *   '#value' => $this->t('Save'),
 * ];
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Button
 */
#[FormElement('submit')]
class Submit extends Button {


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
    return [
      '#executes_submit_callback' => TRUE,
    ] + parent::getInfo();
  }

  /**
   * Create a submit element.
   *
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
  public static function createInstance($executes_submit_callback, $input, $name, $is_button, $submit_button, $limit_validation_errors, $process, $pre_render, $theme_wrappers, $value_callback, $type, $defaults_loaded, ?ElementInfoManager $elementInfoManager = NULL) : static {
    $instance = ($elementInfoManager ?? \Drupal::service('element_info'))->createInstance('submit');
    $instance->executes_submit_callback = $executes_submit_callback;
    $instance->input = $input;
    $instance->name = $name;
    $instance->is_button = $is_button;
    $instance->submit_button = $submit_button;
    $instance->limit_validation_errors = $limit_validation_errors;
    $instance->process = $process;
    $instance->pre_render = $pre_render;
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
      '#executes_submit_callback' => $this->executes_submit_callback,
      '#input' => $this->input,
      '#name' => $this->name,
      '#is_button' => $this->is_button,
      '#submit_button' => $this->submit_button,
      '#limit_validation_errors' => $this->limit_validation_errors,
      '#process' => $this->process,
      '#pre_render' => $this->pre_render,
      '#theme_wrappers' => $this->theme_wrappers,
      '#value_callback' => $this->value_callback,
      '#type' => $this->type,
      '#defaults_loaded' => $this->defaults_loaded,
    ];
  }

}
