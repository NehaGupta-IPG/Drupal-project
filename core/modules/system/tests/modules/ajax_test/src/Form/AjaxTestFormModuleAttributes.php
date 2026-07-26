<?php

declare(strict_types=1);

namespace Drupal\ajax_test\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Test form for ajax_test_form_module_attributes.
 *
 * @internal
 */
class AjaxTestFormModuleAttributes extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ajax_test_form_module_attributes';
  }

  /**
   * Form for testing loading of JavaScript files with different attributes.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['counter']['#prefix'] = '<div id="ajax_test_counter">';
    $form['counter']['#plain_text'] = $form_state->get('count') ?? 0;
    $form['counter']['#suffix'] = '</div>';

    // Button to test that form still does Ajax submissions.
    $form['increase_count_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Increase count button'),
      '#submit' => [[static::class, 'increaseCount']],
      '#ajax' => [
        'callback' => [static::class, 'renderCounter'],
      ],
    ];

    return $form;
  }

  /**
   * Submit callback for the "Increase count" button.
   */
  public static function increaseCount(array $form, FormStateInterface $form_state): void {
    $count = $form_state->get('count') ?? 0;
    $form_state->set('count', $count + 1);
    $form_state->setRebuild();
  }

  /**
   * Ajax callback for the "Increase count" button.
   */
  public static function renderCounter(array $form, FormStateInterface $form_state): AjaxResponse {
    $selector = '#ajax_test_counter';
    $library = [
      '#attached' => [
        'library' => ['ajax_test/module_attributes'],
      ],
    ];
    $counter = $form['counter'];
    $response = new AjaxResponse();
    $response->addCommand(new AppendCommand($selector, $library));
    $response->addCommand(new ReplaceCommand($selector, $counter));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // An empty implementation, as we never submit the actual form.
  }

}
