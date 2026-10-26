<?php

declare(strict_types=1);

namespace Drupal\ajax_forms_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form builder: Builds a form that triggers a simple AJAX callback for multiple usages on the same page.
 *
 * @see \Drupal\ajax_forms_test\Controller\DuplicateForms
 *
 * @internal
 */
class AjaxFormsTestSingularForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ajax_forms_test_singular_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $form['textfield_change'] = [
      '#type' => 'textfield',
      '#title' => 'Textfield Change',
      '#ajax' => [
        'callback' => [static::class, 'textfieldCallback'],
        'event' => 'change',
      ],
    ];

    $form['textfield_input'] = [
      '#type' => 'textfield',
      '#title' => 'Textfield Input',
      '#ajax' => [
        'callback' => [static::class, 'textfieldCallback'],
        'event' => 'input',
      ],
    ];

    return $form;
  }

  public static function textfieldCallback(array $form): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

}
