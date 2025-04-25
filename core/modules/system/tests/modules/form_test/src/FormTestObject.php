<?php

declare(strict_types=1);

namespace Drupal\form_test;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a test form object.
 *
 * @internal
 */
class FormTestObject extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_form_test_object';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['form_test.object'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['element'] = ['#markup' => 'The FormTestObject::buildForm() method was used for this form.'];

    $form['bananas'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bananas'),
    ];
    $form['strawberry'] = [
      '#type' => 'hidden',
      '#value' => 'red',
      '#attributes' => ['id' => 'red-strawberry-hidden-field'],
    ];

    $form['pear_container'] = [
      '#type' => 'container',
      '#deprecated' => [
        'class' => self::class,
        'deprecation_version' => 'drupal:11.2.0',
        'removed_version' => 'drupal:12.0.0',
        'change_record' => 'https://www.drupal.org/project/drupal/issues/3519300',
      ],
      '#tree' => TRUE,
    ];
    $form['pear_container']['pear'] = [
      '#type' => 'hidden',
      '#value' => 1,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    $form['#title'] = 'Test dynamic title';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addStatus($this->t('The FormTestObject::validateForm() method was used for this form.'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addStatus($this->t('The FormTestObject::submitForm() method was used for this form.'));
    $this->config('form_test.object')
      ->set('bananas', $form_state->getValue('bananas'))
      ->save();
    $form_state->getValue('pear_container');
    $form_state->getValue(['pear_container', 'pear']);
  }

}
