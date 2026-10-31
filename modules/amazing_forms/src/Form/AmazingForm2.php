<?php

namespace Drupal\amazing_forms\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Url;

/**
 * AmazingForm class.
 */
class AmazingForm2 extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'amazing_forms_form_step2';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    $form['#prefix'] = '<div id="amazing_form_example_2">';
    $form['#suffix'] = '</div>';


    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];
    $form['age'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Age'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submited'),
      '#ajax' => [
        'callback' => [$this, 'submitModalFormAjaxFinish'],
        'event' => 'click',
      ],
    ];

//    $form['submit']['#attached']['drupalSettings']['ajax'][$form['submit']['#id']]['url'] = Url::fromRoute(
//      '/admin/config/modal_form2',
//      ['query' => [FormBuilderInterface::AJAX_FORM_REQUEST => TRUE]]
//    )->toString();
//    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjaxFinish(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $success_message = 'Form submitted successfully!';
    $response->addCommand(new OpenModalDialogCommand('Success', $success_message, ['width' => '400']));

    return $response;
  }



  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
