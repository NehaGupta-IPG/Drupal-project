<?php

namespace Drupal\system\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form to select the administration theme.
 *
 * @internal
 */
class ThemeAdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'system_themes_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['system.theme'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array $theme_options = NULL) {
    // Administration theme settings.
    $form['admin_theme'] = [
      '#type' => 'details',
      '#title' => $this->t('Administration theme'),
      '#open' => TRUE,
    ];
    $config = $this->config('system.theme');
    $form['admin_theme']['admin_theme'] = [
      '#type' => 'select',
      '#options' => ['' => $this->t('Default theme')] + $theme_options,
      '#title' => $this->t('Administration theme'),
      '#description' => $this->t('Choose "Default theme" to always use the same theme as the rest of the site.'),
      '#default_value' => $config->get('admin'),
    ];
    $form['admin_theme']['favicon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Favicon'),
      '#description' => $this->t('Use default theme favicon instead of administration theme favicon.'),
      '#default_value' => $config->get('favicon'),
      '#states' => [
        'invisible' => [
          'select[name="admin_theme"]' => [
            [
              'value' => 0,
            ],
            [
              'value' => $config->get('default'),
            ],
          ],
        ],
      ],
    ];
    $form['admin_theme']['actions'] = ['#type' => 'actions'];
    $form['admin_theme']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('system.theme')
      ->set('admin', $form_state->getValue('admin_theme'))
      ->set('favicon', $form_state->getValue('favicon'))
      ->save();
  }

}
