<?php

declare(strict_types=1);

namespace Drupal\field_test\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for field_test routes.
 *
 * @internal
 */
class PasswordConfirmTestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'field_test_password_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EntityInterface $entity_1 = NULL, ?EntityInterface $entity_2 = NULL): array {
    $form['pass'] = [
      '#type' => 'password_confirm',
      '#title' => $this->t('Password'),
      '#size' => 25,
      '#attributes' => [
        'class' => ['test-password-class'],
      ],
      '#pass2_attributes' => [
        'class' => ['test-password-confirm-class'],
      ],
      '#required' => TRUE,
    ];

    $form['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#weight' => 100,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()
      ->addStatus($this->t('Your password has been confirmed.'));
  }

}
