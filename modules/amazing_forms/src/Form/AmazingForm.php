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
class AmazingForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'amazing_forms_form_step1';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    $form['#prefix'] = '<div id="amazing_form_example">';
    $form['#suffix'] = '</div>';

    // The status messages that will contain any form errors.
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#attributes' => [
        'class' => ['row', 'expanded'],
        'id' => ['login-page-block'],
      ],
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => [
        'class' => [
          'use-ajax',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitModalFormAjax'],
        'event' => 'click',
        'url' => ''
      ],
    ];
//    $form['submit']['#attached']['drupalSettings']['ajax'][$form['submit']['#id']]['url'] = Url::fromRoute(
//     '/admin/config/modal_form',
//      ['query' => [FormBuilderInterface::AJAX_FORM_REQUEST => TRUE]]
//    )->toString();


    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $modal_form = \Drupal::formBuilder()->getForm('\Drupal\amazing_forms\Form\AmazingForm2');
    $response->addCommand(new OpenModalDialogCommand('Second Form', $modal_form, ['width' => '880']));
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
