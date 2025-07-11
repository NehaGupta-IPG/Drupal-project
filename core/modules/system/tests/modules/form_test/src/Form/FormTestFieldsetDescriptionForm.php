<?php

declare(strict_types=1);

namespace Drupal\form_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form constructor for testing #type 'file' elements.
 *
 * @internal
 */
class FormTestFieldsetDescriptionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_fieldset_description';
  }

  /**
   * Build a form to test fieldset ids.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $id
   *   The id to use for the fieldset.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = '') {
    $form['form_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Author'),
      '#description' => 'Fieldset test for description.',
      '#description_display' => 'invisible',
    ];

    if (!empty($id)) {
      $form['form_fieldset']['#id'] = $id;
    }

    $form['form_fieldset']['name'] = [
      '#type' => 'textfield',
      '#title' => $this
        ->t('Name'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

}
