<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\FormElement;

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
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  public $executes_submit_callback;

  /**
   * @var mixed Automatically converted property
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  public $submit_button;

  /**
   * @var mixed Automatically converted property
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
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
   * {@inheritdoc}
   */
  public function toRenderArray() : array {
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
