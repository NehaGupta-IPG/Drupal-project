<?php

declare(strict_types=1);

namespace Drupal\form_test;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form object for testing deprecated elements.
 */
class DeprecatedElementsFormTestObject extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'deprecated_elements_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->getValue('pear_container');
    $form_state->getValue(['pear_container', 'pear']);
  }

}
