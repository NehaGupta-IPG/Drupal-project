<?php

declare(strict_types=1);

namespace Drupal\form_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Datetime time sub-element test form.
 */
class FormTestDatetimeTimeTest extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_datetime_time';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['datetime_with_seconds_html5'] = [
      '#type' => 'datetime',
      '#title' => $this->t('HTML5 datetime with seconds'),
      '#default_value' => NULL,
      '#date_time_element' => 'time',
      '#date_time_format' => 'H:i:s',
    ];

    $form['datetime_without_seconds_html5'] = [
      '#type' => 'datetime',
      '#title' => $this->t('HTML5 datetime without seconds'),
      '#default_value' => NULL,
      '#date_time_element' => 'time',
      '#date_time_format' => 'H:i',
    ];

    $form['datetime_with_seconds_text'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Text field datetime with seconds'),
      '#default_value' => NULL,
      '#date_time_element' => 'text',
      '#date_time_format' => 'H:i:s',
    ];

    $form['datetime_without_seconds_text'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Text field datetime without seconds'),
      '#default_value' => NULL,
      '#date_time_element' => 'text',
      '#date_time_format' => 'H:i',
    ];

    $form['datetime_unusual_format'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Datetime in an unusual format'),
      '#default_value' => NULL,
      '#date_date_element' => 'text',
      '#date_date_format' => 'd/m/Y',
      '#date_time_element' => 'text',
      '#date_time_format' => 'H-i-s',
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();

    $this->messenger()->addStatus('Success');
  }

}
