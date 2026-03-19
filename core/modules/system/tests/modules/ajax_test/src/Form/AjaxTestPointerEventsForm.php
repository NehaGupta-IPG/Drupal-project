<?php

declare(strict_types=1);

namespace Drupal\ajax_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Test form for pointer-events restoration after AJAX element replacement.
 *
 * Simulates the scenario where an AJAX response replaces the wrapper
 * containing both the AJAX triggering element and the form submit button,
 * which is what happens with file upload widgets.
 *
 * @internal
 */
class AjaxTestPointerEventsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ajax_test_pointer_events_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Wrapper that will be replaced by AJAX, simulating file upload behavior.
    $form['ajax_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'ajax-pe-test-wrapper',
      ],
    ];

    $form['ajax_wrapper']['select_trigger'] = [
      '#type' => 'select',
      '#title' => $this->t('AJAX trigger'),
      '#options' => [
        '' => $this->t('- Select -'),
        'option_a' => $this->t('Option A'),
        'option_b' => $this->t('Option B'),
      ],
      '#default_value' => $form_state->getValue('select_trigger', ''),
      '#ajax' => [
        'callback' => '::ajaxReplaceWrapper',
        'wrapper' => 'ajax-pe-test-wrapper',
        'event' => 'change',
      ],
    ];

    // A text field inside the wrapper to act as an additional AJAX peer.
    $form['ajax_wrapper']['textfield'] = [
      '#type' => 'textfield',
      '#title' => $this->t('A text field'),
      '#default_value' => $form_state->getValue('textfield', ''),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * AJAX callback that replaces the wrapper, simulating file upload response.
   *
   * Returns the rebuilt wrapper, which causes the insert command to use
   * replaceWith, detaching original DOM elements and inserting new ones.
   */
  public function ajaxReplaceWrapper(array &$form, FormStateInterface $form_state): array {
    return $form['ajax_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addStatus($this->t('Form submitted successfully.'));
  }

}
