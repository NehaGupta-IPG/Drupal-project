<?php

namespace Drupal\views_ui\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Views;

/**
 * Form builder for the advanced admin settings page.
 *
 * @internal
 */
class AdvancedSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'views_ui_admin_settings_advanced';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['views.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['cache'] = [
      '#type' => 'details',
      '#title' => $this->t('Caching'),
      '#open' => TRUE,
    ];

    $form['cache']['clear_cache'] = [
      '#type' => 'submit',
      '#value' => $this->t("Clear Views' cache"),
      '#submit' => ['::cacheSubmit'],
    ];

    $form['debug'] = [
      '#type' => 'details',
      '#title' => $this->t('Debugging'),
      '#open' => TRUE,
    ];

    $form['debug']['sql_signature'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add Views signature to all SQL queries'),
      '#description' => $this->t("All Views-generated queries will include the name of the views and display 'view-name:display-name' as a string at the end of the SELECT clause. This makes identifying Views queries in database server logs simpler, but should only be used when troubleshooting."),
      '#config_target' => 'views.settings:sql_signature',
    ];

    $options = Views::fetchPluginNames('display_extender');
    if (!empty($options)) {
      $form['extenders'] = [
        '#type' => 'details',
        '#title' => $this->t('Display extenders'),
        '#open' => TRUE,
      ];
      $form['extenders']['display_extenders'] = [
        '#config_target' => new ConfigTarget(
          'views.settings',
          'display_extenders',
          fromConfig: 'array_filter',
        ),

        '#options' => $options,
        '#type' => 'checkboxes',
        '#description' => $this->t('Select extensions of the views interface.'),
      ];
    }

    return $form;
  }

  /**
   * Submission handler to clear the Views cache.
   */
  public function cacheSubmit() {
    views_invalidate_cache();
    $this->messenger()->addStatus($this->t('The cache has been cleared.'));
  }

}
