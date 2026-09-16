<?php

namespace Drupal\Core\Form;

/**
 * Provides a generic base class for a confirmation form.
 */
abstract class ConfirmFormBase extends FormBase implements ConfirmFormInterface {

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Confirm');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormName() {
    @trigger_error(
      'ConfirmFormBase::getFormName() is deprecated in drupal:11.2.0 and will be removed in drupal:12.0.0. 
      Implementations should remove this method. See https://www.drupal.org/node/3505037',
      E_USER_DEPRECATED
    );
    return 'confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#title'] = $this->getQuestion();

    $form['#attributes']['class'][] = 'confirmation';
    $form['description'] = ['#markup' => $this->getDescription()];
    $form[$this->getFormName()] = ['#type' => 'hidden', '#value' => 1];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->getConfirmText(),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = ConfirmFormHelper::buildCancelLink($this, $this->getRequest());

    // By default, render the form using theme_confirm_form().
    if (!isset($form['#theme'])) {
      $form['#theme'] = 'confirm_form';
    }
    return $form;
  }

}
