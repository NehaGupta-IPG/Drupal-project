<?php

declare(strict_types=1);

namespace Drupal\form_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Form constructor to test required radios.
 *
 * @internal
 */
final class FormTestRadiosRequiredForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'form_test_radios_required';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
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
    $form['radios-required-with-default'] = [
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
    $form['radios-not-required'] = [
      '#type' => 'radios',
      '#title' => 'Radios not required',
      '#options' => [
        0 => 'Zero',
        1 => 'One',
        2 => 'Two',
      ],
      '#required' => FALSE,
    ];
    $form['submit'] = ['#type' => 'submit', '#value' => 'Submit'];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setResponse(new JsonResponse($form_state->getValues()));
  }

}
