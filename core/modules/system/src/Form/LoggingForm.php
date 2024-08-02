<?php

namespace Drupal\system\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
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
      '#title' => $this->t('Enable logging of deprecation messages'),
      '#config_target' => 'system.logging:log_deprecations',
      '#description' => $this->t('It is recommended that sites running on production environments do not log any deprecations.'),
    ];

    $form['deprecations_ignored_file_patterns'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Ignored deprecation source file patterns'),
      '#config_target' => new ConfigTarget(
        'system.logging',
        'deprecations_ignored_file_patterns',
        static::class . '::arrayToMultiLineString',
        static::class . '::multiLineStringToArray'),
      '#description' => $this->t('List of source file patterns from which deprecations should be ignored, one per line. Regex allowed.'),
      '#states' => [
        'visible' => [
          ':input[name="log_deprecations"]' => ['checked' => TRUE],
        ],
      ],
      '#rows' => 3,
    ];
    $form['ignored_deprecations'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Ignored deprecation messages'),
      '#config_target' => new ConfigTarget(
        'system.logging',
        'ignored_deprecations',
        static::class . '::arrayToMultiLineString',
        static::class . '::multiLineStringToArray'),
      '#description' => $this->t('List of ignored deprecation messages, one per line'),
      '#states' => [
        'visible' => [
          ':input[name="log_deprecations"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Prepares the submitted value to be stored as an array.
   *
   * @param string $value
   *   The submitted value.
   *
   * @return array
   *   The value to be stored in config.
   */
  public static function multiLineStringToArray(string $value): array {
    return array_map('trim', explode("\n", trim($value)));
  }

  /**
   * Prepares the stored array to be displayed in the form.
   *
   * @param array $value
   *   The value saved in config.
   *
   * @return string
   *   The value of the form element.
   */
  public static function arrayToMultiLineString(array $value): string {
    return implode("\n", $value);
  }

}
