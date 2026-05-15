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
    // TODO should we clear cache here, or even not use caching at all for this plugin type?
    $feature_flag_definitions = \Drupal::service('plugin.manager.system_feature_flag')->getDefinitions();
    dump($feature_flag_definitions);

    $feature_flag_options = [];
    foreach ($feature_flag_definitions as $feature_flag_definition_id => $feature_flag_definition) {
      // TODO: need docs link, version info, etc.
      $feature_flag_options[$feature_flag_definition_id] = $feature_flag_definition['title'];
    }

    $form['feature_flags'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t("Feature Flags"),
      '#options' => $feature_flag_options,
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
