<?php

declare(strict_types=1);

namespace Drupal\max_input_vars_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;

class MaxInputVarsTestForm extends FormBase implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'max-input-vars-test-form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $max_vars = ini_get('max_input_vars');
    for ($i = 0; $i < $max_vars + 1; $i ++) {
      $form["box-$i"] = [
        '#type' => 'checkbox',
        '#title' => $i,
        '#default_value' => FALSE,
      ];
    }
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Submit',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addError('This should not happen');
  }

}
