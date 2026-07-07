<?php

namespace Drupal\language\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
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
    $form['default_langcode'] = [
      '#type' => 'language_select',
      '#title' => $this->t("Site's administration language"),
      '#languages' => LanguageInterface::STATE_CONFIGURABLE | LanguageInterface::STATE_SITE_DEFAULT,
      '#config_target' => new ConfigTarget(
        'language.negotiation',
        'admin_language.default_langcode',
        // Convert NULL from config to empty string for the form element.
        fromConfig: fn($value) => $value ?? '',
        // Convert empty string from the form element to NULL in config.
        toConfig: fn($value) => $value === '' ? NULL : $value,
      ),
      '#description' => $this->t('Unless there is a user-selected administration language, this will be used. If set to "No global preference" and there was no user preference, language will be determined by other negotiation methods.'),
      '#empty_option' => $this->t('No global preference'),
      '#empty_value' => '',
    ];

    $form['allow_user_change'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow users to set their own administration language'),
      '#config_target' => 'language.negotiation:admin_language.allow_user_change',
    ];

    return parent::buildForm($form, $form_state);
  }

}
