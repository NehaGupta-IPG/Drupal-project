<?php

declare(strict_types=1);

namespace Drupal\ajax_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a multistep form with no AJAX or dialog handling of its own.
 *
 * @internal
 */
class AjaxDialogChildForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ajax_dialog_child_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['answer'] = ['#type' => 'textfield', '#title' => 'Dialog answer', '#required' => TRUE];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $form_state->get('confirm') ? 'Confirm answer' : 'Continue',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$form_state->get('confirm')) {
      $form_state->set('confirm', TRUE)->setRebuild();
    }
    else {
      \Drupal::state()->set('ajax_dialog_child', $form_state->getValue('answer'));
      $form_state->setRedirect('ajax_test.dialog');
    }
  }

}
