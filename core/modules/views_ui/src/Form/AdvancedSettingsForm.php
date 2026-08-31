<?php

namespace Drupal\views_ui\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Form builder for the advanced admin settings page.
 *
 * @internal
 */
#[Route(
  path: '/admin/structure/views/settings/advanced',
  name: 'views_ui.settings_advanced',
  requirements: [
    '_permission' => 'administer views',
  ],
  defaults: [
    '_title' => new TranslatableMarkup('Advanced Views settings'),
  ],
)]
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

    $config = $this->config('views.settings');
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

      '#default_value' => $config->get('sql_signature'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('views.settings')
      ->set('sql_signature', $form_state->getValue('sql_signature'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Submission handler to clear the Views cache.
   */
  public function cacheSubmit() {
    views_invalidate_cache();
    $this->messenger()->addStatus($this->t('The cache has been cleared.'));
  }

}
