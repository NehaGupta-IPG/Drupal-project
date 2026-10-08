<?php

declare(strict_types=1);

namespace Drupal\ajax_test\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for testing the announcement of AJAX progress.
 *
 * @internal
 */
class AjaxTestProgressAnnounceForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ajax_test_progress_announce_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $buttons = [
      'default' => [],
      'custom_message' => [
        'progress' => [
          'type' => 'throbber',
          'message' => 'Hold on',
        ],
      ],
      'no_message' => [
        'progress' => [
          'type' => 'throbber',
        ],
      ],
      'no_announce' => [
        'progress' => [
          'type' => 'throbber',
          'announce' => FALSE,
        ],
      ],
    ];
    foreach ($buttons as $name => $ajax) {
      $form[$name] = [
        '#type' => 'submit',
        '#name' => $name,
        '#value' => $name,
        '#ajax' => $ajax + ['callback' => '::ajaxCallback'],
      ];
    }
    return $form;
  }

  /**
   * AJAX callback that returns an empty response.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An empty AJAX response.
   */
  public function ajaxCallback(array $form, FormStateInterface $form_state): AjaxResponse {
    return new AjaxResponse();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

}
