<?php

namespace Drupal\ajax_test\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form to test concurrent AJAX requests.
 */
class AjaxTestConcurrentRequestsForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'ajax_test_concurrent_requests_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form['slow'] = [
      '#type' => 'submit',
      '#value' => $this->t('Slow request'),
      '#name' => 'op_slow',
      '#request_time' => 5,
      '#submit' => [[static::class, 'increaseCounterSubmit']],
      '#ajax' => [
        'callback' => [static::class, 'increaseCounterAjax'],
      ],
    ];

    $form['fast'] = [
      '#type' => 'submit',
      '#value' => $this->t('Fast request'),
      '#name' => 'op_fast',
      '#submit' => [[static::class, 'increaseCounterSubmit']],
      '#ajax' => [
        'callback' => [static::class, 'increaseCounterAjax'],
      ],
    ];

    $form['chained'] = [
      '#type' => 'submit',
      '#value' => $this->t('Chained requests'),
      '#name' => 'op_chained',
      '#submit' => [[static::class, 'rebuildSubmit']],
      '#ajax' => [
        'callback' => [static::class, 'chainedAjax'],
      ],
    ];

    $form['message'] = [
      '#type' => 'container',
      '#id' => 'message-wrapper',
    ];

    $form['non_blocking'] = [
      '#type' => 'link',
      '#title' => $this->t('Non-blocking request'),
      '#url' => Url::fromRoute('ajax_test.admin.theme'),
      '#ajax' => [
        'method' => 'html',
        'wrapper' => 'message-wrapper',
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $this->messenger()->addStatus($this->t('Submitted slow counter: @counter.', [
      '@counter' => $form_state->get(['counter', 'op_slow']) ?? 0,
    ]));
    $this->messenger()->addStatus($this->t('Submitted fast counter: @counter.', [
      '@counter' => $form_state->get(['counter', 'op_fast']) ?? 0,
    ]));
  }

  /**
   * Submit callback that sets the form to be rebuilt.
   */
  public static function rebuildSubmit(array $form, FormStateInterface $form_state)
  {
    $form_state->setRebuild();
  }

  /**
   * Submit callback that increases the button counter by one.
   */
  public static function increaseCounterSubmit(array $form, FormStateInterface $form_state)
  {
    $button = $form_state->getTriggeringElement();
    sleep($button['#request_time'] ?? 0);

    $path = ['counter', $button['#name']];
    $counter = $form_state->get($path) ?? 0;
    $form_state->set($path, $counter);

    $form_state->setRebuild();
  }

  /**
   * Empty AJAX callback.
   */
  public static function increaseCounterAjax(array $form, FormStateInterface $form_state)
  {
    return new AjaxResponse();
  }

  /**
   * AJAX callback that triggers a click on the non-blocking link.
   */
  public static function chainedAjax(array $form, FormStateInterface $form_state)
  {
    $response = new AjaxResponse();

    $selector = $form['non_blocking']['#attributes']['data-drupal-selector'];
    $response->addCommand(new InvokeCommand("[data-drupal-selector='$selector']", 'trigger', ['click']));

    return $response;
  }

}
