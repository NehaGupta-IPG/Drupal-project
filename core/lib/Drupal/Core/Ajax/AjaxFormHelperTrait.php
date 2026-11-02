<?php

namespace Drupal\Core\Ajax;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a helper to for submitting an AJAX form.
 *
 * @internal
 */
trait AjaxFormHelperTrait {

  use AjaxHelperTrait;

  /**
   * Submit form dialog #ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response that display validation error messages or represents a
   *   successful submission.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    if ($form_state->hasAnyErrors() || $form_state->isRebuilding()) {
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -1000,
      ];
      $form['#sorted'] = FALSE;
      $response = new AjaxResponse();

      // If a wrapper is configured, let Form API use it to replace the form.
      // Rebuilding may change the form's selector, so the new selector may no
      // longer match the form that is still displayed in the browser.
      $wrapper = $form_state->getTriggeringElement()['#ajax']['wrapper'] ?? NULL;
      $selector = is_string($wrapper) && $wrapper !== ''
        ? NULL
        : '[data-drupal-selector="' . $form['#attributes']['data-drupal-selector'] . '"]';
      $response->addCommand(new ReplaceCommand($selector, $form));
    }
    else {
      $response = $this->successfulAjaxSubmit($form, $form_state);
    }
    return $response;
  }

  /**
   * Allows the form to respond to a successful AJAX submission.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response.
   */
  abstract protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state);

}
