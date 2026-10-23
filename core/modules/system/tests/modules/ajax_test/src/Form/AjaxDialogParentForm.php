<?php

declare(strict_types=1);

namespace Drupal\ajax_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides an unfinished form that receives a separate dialog's result.
 *
 * @internal
 */
class AjaxDialogParentForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ajax_dialog_parent_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Disable form-wide AJAX explicitly. The final save must still submit
    // normally, even though another button opens a form in a dialog.
    $form['#ajax'] = FALSE;
    $form['#prefix'] = '<div id="parent-form-wrapper">';
    $form['#suffix'] = '</div>';
    $form['draft'] = ['#type' => 'textfield', '#title' => 'Draft'];
    $form['required'] = ['#type' => 'textfield', '#title' => 'Required', '#required' => TRUE];
    $form['answer'] = ['#type' => 'textfield', '#title' => 'Answer'];
    $form['open'] = [
      '#type' => 'button',
      '#submit_button' => FALSE,
      '#value' => 'Open dialog',
      '#limit_validation_errors' => [],
      '#url' => Url::fromRoute('ajax_test.dialog_child'),
      '#ajax' => ['callback' => 'form_ajax_response_builder:openDialog'],
      '#dialog_submit' => ['::acceptDialog'],
    ];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => 'Save parent'];
    return $form;
  }

  /**
   * Copies the dialog's answer into the unfinished calling form.
   */
  public function acceptDialog(FormStateInterface $dialog_state, array $element, FormStateInterface $form_state): void {
    $form_state->getUserInput()['answer'] = $dialog_state->getValue('answer');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    \Drupal::state()->set('ajax_dialog_parent', $form_state->getValues());
    $this->messenger()->addStatus('Parent saved.');
  }

}
