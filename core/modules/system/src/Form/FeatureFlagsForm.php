<?php

declare(strict_types=1);

namespace Drupal\system\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * TODO: class docs.
 */
class FeatureFlagsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'system_feature_flags_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Uncomment this line if you change the base class.
    // $form = parent::buildForm($form, $form_state);

    $form['feature_flags'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t("Feature Flags"),
      '#options' => [],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
