<?php

namespace Drupal\system\Form;

use Drupal\Core\Error\ErrorDisplayLevel;
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
        ErrorDisplayLevel::Hide->value => $this->t('None'),
        ErrorDisplayLevel::Some->value => $this->t('Errors and warnings'),
        ErrorDisplayLevel::All->value => $this->t('All messages'),
        ErrorDisplayLevel::Verbose->value => $this->t('All messages, with backtrace information'),
      ],
      '#description' => $this->t('It is recommended that sites running on production environments do not display any errors.'),
    ];

    return parent::buildForm($form, $form_state);
  }

}
