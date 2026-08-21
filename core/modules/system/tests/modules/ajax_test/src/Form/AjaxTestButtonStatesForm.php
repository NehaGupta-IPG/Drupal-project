<?php

declare(strict_types=1);

namespace Drupal\ajax_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for testing #states on dialog buttons (issue #3008172).
 *
 * @internal
 */
class AjaxTestButtonStatesForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ajax_test_button_states_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your Name'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your Email'),
      '#required' => TRUE,
    ];

    // Visible only when name = "Admin".
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#required' => TRUE,
      '#states' => [
        'visible' => [':input[name="name"]' => ['value' => 'Admin']],
      ],
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
    ];

    // Hidden when name = "Hide Button".
    $form['actions'] = [
      '#type' => 'actions',
      '#states' => [
        'invisible' => [':input[name="name"]' => ['value' => 'Hide Button']],
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send message'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Form submission not needed for this test.
  }

}
