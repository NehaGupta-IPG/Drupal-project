<?php

namespace Drupal\language\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * Configure the administration language negotiation method for this site.
 *
 * @internal
 */
class NegotiationAdminLanguageForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'language_negotiation_configure_admin_language_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['language.negotiation'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('language.negotiation');

    $form['default_langcode'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Default administration language'),
      '#languages' => LanguageInterface::STATE_CONFIGURABLE | LanguageInterface::STATE_SITE_DEFAULT,
      '#default_value' => $config->get('admin_language.default_langcode'),
      '#description' => $this->t('The language to use for administration by default.'),
    ];

    $form['allow_user_change'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow users to set their own administration language'),
      '#default_value' => $config->get('admin_language.allow_user_change') ?? FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('language.negotiation')
      ->set('admin_language.default_langcode', $form_state->getValue('default_langcode'))
      ->set('admin_language.allow_user_change', $form_state->getValue('allow_user_change'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
