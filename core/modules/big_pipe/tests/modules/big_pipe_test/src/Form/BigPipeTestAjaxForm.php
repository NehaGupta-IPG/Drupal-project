<?php

declare(strict_types=1);

namespace Drupal\big_pipe_test\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Form with a #ajax-enabled submit button, for BigPipe placeholder testing.
 *
 * @internal
 */
class BigPipeTestAjaxForm extends FormBase implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'big_pipe_test_ajax_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#token'] = FALSE;

    $form['result'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'big-pipe-test-ajax-form-result'],
      'text' => ['#markup' => 'Not clicked yet.'],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Click me'),
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'big-pipe-test-ajax-form-result',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * AJAX callback: replaces the result container with confirmation markup.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#big-pipe-test-ajax-form-result', 'Clicked!'));
    return $response;
  }

  /**
   * Lazy builder callback: renders this form, wrapped as a placeholder.
   *
   * @return array
   *   The form's render array.
   */
  public static function buildAsPlaceholder(): array {
    $form = \Drupal::formBuilder()->getForm(static::class);
    // Force this placeholder to always resolve via BigPipe, regardless of
    // auto-placeholdering heuristics, to reliably reproduce the command-
    // ordering bug this test guards against.
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['buildAsPlaceholder'];
  }

}
