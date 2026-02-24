<?php

namespace Drupal\language\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure the session language negotiation method for this site.
 *
 * @internal
 */
class NegotiationSessionForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'language_negotiation_configure_session_form';
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
    $form['language_negotiation_session_param'] = [
      '#title' => $this->t('Request/session parameter'),
      '#type' => 'textfield',
      '#config_target' => 'language.negotiation:session.parameter',
      '#description' => $this->t('Name of the request/session parameter used to determine the desired language.'),
    ];

    $form_state->setRedirect('language.negotiation');

    return parent::buildForm($form, $form_state);
  }

}
