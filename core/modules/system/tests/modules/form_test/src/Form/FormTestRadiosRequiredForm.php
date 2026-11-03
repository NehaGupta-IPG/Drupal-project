<?php

namespace Drupal\form_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Form constructor to test required radios.
 *
 * @internal
 */
class FormTestRadiosRequiredForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_radios_required';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['radios-required'] = [
      '#type' => 'radios',
      '#title' => 'Radios required',
      '#options' => [
        0 => 'Zero',
        1 => 'One',
        2 => 'Two',
      ],
      '#required' => TRUE,
    ];
    $form['radios-required-default'] = [
      '#type' => 'radios',
      '#title' => 'Radios required with default value',
      '#options' => [
        0 => 'Zero',
        1 => 'One',
        2 => 'Two',
      ],
      '#required' => TRUE,
      '#default_value' => 1,
    ];
    $form['submit'] = ['#type' => 'submit', '#value' => 'Submit'];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setResponse(new JsonResponse($form_state->getValues()));
  }

}
