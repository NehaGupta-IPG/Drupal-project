<?php

namespace Drupal\language\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure the content entity language negotiation method for this site.
 *
 * @internal
 */
class NegotiationContentEntityForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'language_negotiation_configure_content_entity_form';
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
    $form['language_negotiation_menu_lang_type_interface'] = [
      '#title' => $this->t('Force menu follow Interface Language'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('menu_lang_type_interface'),
    ];

    $form_state->setRedirect('language.negotiation');

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('language.negotiation')
      ->set('menu_lang_type_interface', $form_state->getValue('language_negotiation_menu_lang_type_interface'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
