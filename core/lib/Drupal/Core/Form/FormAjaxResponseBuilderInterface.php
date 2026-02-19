<?php

namespace Drupal\Core\Form;

use Symfony\Component\HttpFoundation\Request;

/**
 * Provides an interface for building AJAX form responses.
 */
interface FormAjaxResponseBuilderInterface {

  /**
   * Builds a response for an AJAX form.
   *
   * @param Request $request
   *   The current request.
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param FormStateInterface $form_state
   *   The current state of the form.
   * @param array $commands
   *   An array of AJAX commands to apply to the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response representing the form and its AJAX commands.
   *
   * @throws HttpException
   *   Thrown if the AJAX callback is not a callable.
   */
  public function buildResponse(Request $request, array $form, FormStateInterface $form_state, array $commands);

}
