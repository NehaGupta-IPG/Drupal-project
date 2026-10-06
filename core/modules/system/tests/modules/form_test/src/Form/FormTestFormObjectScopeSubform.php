<?php

declare(strict_types=1);

namespace Drupal\form_test\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;

/**
 * Form constructor for a form embedded with the #form_object property.
 *
 * @internal
 */
class FormTestFormObjectScopeSubform extends FormTestFormObjectScopeForm {

  public function __construct(
    string $label,
    protected string $name,
    protected bool $nested = FALSE,
  ) {
    $this->label = $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_form_object_scope_subform';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, bool $scoped = TRUE) {
    $form['#process'][] = '::processRoot';
    $form['#after_build'][] = '::afterBuildRoot';
    $form['text'] = [
      '#type' => 'textfield',
      '#title' => 'Text',
      '#value_callback' => [static::class, 'valueCallback'],
      '#element_validate' => ['::elementValidate'],
    ];
    $form['button'] = [
      '#type' => 'submit',
      '#value' => 'Button',
      '#name' => $this->name . '_button',
      '#validate' => ['::validateButton'],
      '#submit' => ['::submitButton'],
      '#ajax' => ['callback' => '::ajaxButton'],
    ];
    if (!$this->nested) {
      return $form;
    }

    $form['button']['#limit_validation_errors'] = [['child']];
    $form['fallback'] = [
      '#type' => 'submit',
      '#value' => 'Fallback',
      '#name' => 'fallback',
    ];
    if ($form_state->get('stale') !== TRUE) {
      $form['stale'] = [
        '#type' => 'submit',
        '#value' => 'Stale',
        '#name' => 'stale',
        '#submit' => ['::submitStale'],
        '#ajax' => ['callback' => '::ajaxButton'],
      ];
    }

    $grandchild = new self('grandchild', 'grandchild');
    $form['grandchild'] = [];
    $form['grandchild'] = $grandchild->buildForm($form['grandchild'], SubformState::createForSubform($form['grandchild'], $form, $form_state, $grandchild));
    $form['grandchild']['#form_object'] = $grandchild;
    $form['after_grandchild'] = [
      '#process' => ['::processElement'],
    ];

    return $form;
  }

}
