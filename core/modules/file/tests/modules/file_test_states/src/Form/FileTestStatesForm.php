<?php

declare(strict_types=1);

namespace Drupal\file_test_states\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * A simple form with a file and manage_file to test states.
 */
class FileTestStatesForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'file_test_states_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['toggle'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Toggle fields'),
    ];
    $form['managed_file_initially_visible'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Managed File Initially Visible'),
      '#states' => [
        'visible'  => [':input[name="toggle"]' => ['checked' => FALSE]],
      ],
    ];
    $form['managed_file_initially_hidden'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Managed File Initially Hidden'),
      '#states' => [
        'visible'  => [':input[name="toggle"]' => ['checked' => TRUE]],
      ],
    ];
    $form['managed_file_initially_optional'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Managed File Initially Optional'),
      '#states' => [
        'required'  => [':input[name="toggle"]' => ['checked' => TRUE]],
      ],
    ];
    $form['details'] = [
      '#type' => 'details',
      '#title' => $this->t('Details'),
      '#open' => TRUE,
    ];
    $form['details']['managed_file_in_details'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Managed File In Details'),
      '#states' => [
        'visible' => [':input[name="toggle"]' => ['checked' => TRUE]],
      ],
    ];
    $form['details']['textfield_in_details'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Textfield In Details'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
