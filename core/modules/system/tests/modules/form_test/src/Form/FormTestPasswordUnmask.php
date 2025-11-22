<?php

declare(strict_types=1);

namespace Drupal\form_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form constructor for testing #type 'password_unmask' elements.
 */
class FormTestPasswordUnmask extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_password_unmask';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['password_one'] = [
      '#title' => $this->t('Password One'),
      '#type' => 'password_unmask',
      '#size' => 25,
      '#attributes' => [
        'data-drupal-strength-indicator' => TRUE,
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
