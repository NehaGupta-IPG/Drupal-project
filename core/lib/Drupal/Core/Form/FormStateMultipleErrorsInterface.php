<?php

namespace Drupal\Core\Form;

/**
 * Provides additional functionality for multiple errors per element.
 *
 * @ingroup form_api
 */
interface FormStateMultipleErrorsInterface {

  /**
   * Returns an associative array of all errors.
   *
   * @return array
   *   An array of all errors, keyed by the name of the form element. The value
   *   is an array of errors.
   */
  public function getAllErrors();

  /**
   * Returns the error messages filed against the given form element.
   *
   * Form errors higher up in the form structure override deeper errors as well
   * as errors on the element itself.
   *
   * @param array $element
   *   The form element to check for errors.
   *
   * @return array
   *   The error message for this element.
   */
  public function getAllErrorsForElement(array $element);

}
