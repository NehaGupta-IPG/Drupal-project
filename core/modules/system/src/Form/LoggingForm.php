<?php

namespace Drupal\system\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;

/**
 * Configure logging settings for this site.
 *
 * @internal
 */
class LoggingForm extends ConfigFormBase {
  use RedundantEditableConfigNamesTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'system_logging_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['error_level'] = [
      '#type' => 'radios',
      '#title' => $this->t('Error messages to display'),
      '#config_target' => 'system.logging:error_level',
      '#options' => [
        ERROR_REPORTING_HIDE => $this->t('None'),
        ERROR_REPORTING_DISPLAY_SOME => $this->t('Errors and warnings'),
        ERROR_REPORTING_DISPLAY_ALL => $this->t('All messages'),
        ERROR_REPORTING_DISPLAY_VERBOSE => $this->t('All messages, with backtrace information'),
      ],
      '#description' => $this->t('It is recommended that sites running on production environments do not display any errors.'),
    ];

    $form['log_deprecations'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Should deprecations be logged?'),
      '#config_target' => 'system.logging:log_deprecations',
      '#description' => $this->t('It is recommended that sites running on production environments do not log any deprecations.'),
    ];

    $form['deprecations_ignored_file_patterns'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Ignored deprecation source file patterns'),
      '#config_target' => 'system.logging:deprecations_ignored_file_patterns',
      '#description' => $this->t('List of source file patterns from which deprecations should be ignored. Regex allowed.'),
      '#states' => [
        'visible' => [
          ':input[name="log_deprecations"]' => ['checked' => TRUE],
        ],
      ],
      '#rows' => 3,
    ];
    $form['ignored_deprecations'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Ignored deprecations'),
      '#config_target' => 'system.logging:ignored_deprecations',
      '#description' => $this->t('List of ignored deprecation messages, one per line'),
      '#states' => [
        'visible' => [
          ':input[name="log_deprecations"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

}
