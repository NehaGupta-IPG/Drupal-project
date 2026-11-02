<?php

declare(strict_types=1);

namespace Drupal\ajax_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Dummy GET form for testing AJAX callbacks.
 *
 * @internal
 */
class AjaxTestGetForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ajax_test_get_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#action'] = Url::fromRoute('ajax_test.get_form')->toString();
    $form['request_method'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'ajax-test-request-method',
      ],
    ];
    $form['get_request'] = [
      '#type' => 'submit',
      '#value' => $this->t('GET request'),
      '#attributes' => [
        'data-ajax-http-method' => 'GET',
      ],
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#ajax' => [
        'url' => Url::fromRoute('ajax_test.get_callback'),
        'event' => 'click',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

}
