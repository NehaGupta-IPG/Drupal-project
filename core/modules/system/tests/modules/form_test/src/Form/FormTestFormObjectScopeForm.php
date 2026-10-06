<?php

declare(strict_types=1);

namespace Drupal\form_test\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\SubformStateInterface;

/**
 * Form constructor for testing the #form_object property.
 *
 * @internal
 */
class FormTestFormObjectScopeForm extends FormBase {

  /**
   * The callbacks run, as "callback object/form object" strings.
   *
   * The object is the one the callback ran on, "-" for static callbacks. The
   * form object is the one returned by the form state.
   *
   * @var string[]
   */
  public static array $log = [];

  /**
   * The child][text value read by the callbacks, keyed by callback.
   *
   * @var array<string, mixed>
   */
  public static array $values = [];

  /**
   * The label telling form object instances apart.
   */
  protected string $label = 'parent';

  /**
   * Gets the label telling form object instances apart.
   */
  public function getLabel(): string {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_form_object_scope';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param bool $scoped
   *   Whether to set #form_object on the child form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, bool $scoped = TRUE) {
    $build = ($form_state->get('build') ?? 0) + 1;
    $form_state->set('build', $build);

    $form['#process'][] = '::processRoot';
    $form['#after_build'][] = '::afterBuildRoot';
    $form['parent_text'] = [
      '#type' => 'textfield',
      '#title' => 'Parent text',
      '#required' => TRUE,
      '#element_validate' => ['::elementValidate'],
    ];

    $child = new FormTestFormObjectScopeSubform('child' . $build, 'child', TRUE);
    $form['child'] = ['#tree' => TRUE];
    $form['child'] = $child->buildForm($form['child'], SubformState::createForSubform($form['child'], $form, $form_state, $child));
    $form['child']['parent_button'] = [
      '#type' => 'submit',
      '#value' => 'Parent button',
      '#name' => 'parent_button',
      '#validate' => [[$this, 'validateButton']],
      '#submit' => [[$this, 'submitButton']],
    ];
    if ($scoped) {
      $form['child']['#form_object'] = $child;
    }

    $form['sibling'] = [
      '#process' => ['::processElement'],
    ];
    $form['embed'] = [
      '#tree' => TRUE,
      '#process' => ['::processEmbed'],
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Submit',
    ];

    return $form;
  }

  /**
   * Form API #process callback for form roots.
   */
  public function processRoot(array $element, FormStateInterface $form_state, array &$complete_form): array {
    $this->log(__FUNCTION__, $form_state);
    return $element;
  }

  /**
   * Form API #after_build callback for form roots.
   */
  public function afterBuildRoot(array $element, FormStateInterface $form_state): array {
    $this->log(__FUNCTION__, $form_state);
    return $element;
  }

  /**
   * Form API #process callback.
   */
  public function processElement(array $element, FormStateInterface $form_state, array &$complete_form): array {
    $this->log(__FUNCTION__, $form_state);
    return $element;
  }

  /**
   * Form API #process callback embedding a form object.
   */
  public function processEmbed(array $element, FormStateInterface $form_state, array &$complete_form): array {
    $this->log(__FUNCTION__, $form_state);
    $embedded = new FormTestFormObjectScopeSubform('embedded', 'embedded');
    $element['#form_object'] = $embedded;
    $element['subform'] = [];
    $element['subform'] = $embedded->buildForm($element['subform'], SubformState::createForSubform($element['subform'], $element, $form_state, $embedded));
    return $element;
  }

  /**
   * Form API #value_callback callback.
   */
  public static function valueCallback(array &$element, mixed $input, FormStateInterface $form_state): mixed {
    static::addLog(__FUNCTION__, '-', $form_state);
    return $input === FALSE ? NULL : $input;
  }

  /**
   * Form API #element_validate callback.
   */
  public function elementValidate(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $this->log(__FUNCTION__, $form_state);
  }

  /**
   * Button #validate callback.
   */
  public function validateButton(array &$form, FormStateInterface $form_state): void {
    $this->log(__FUNCTION__, $form_state);
  }

  /**
   * Button #submit callback.
   */
  public function submitButton(array &$form, FormStateInterface $form_state): void {
    $this->log(__FUNCTION__, $form_state);
    $form_state->setRebuild();
  }

  /**
   * Button #submit callback removing the button on rebuild.
   */
  public function submitStale(array &$form, FormStateInterface $form_state): void {
    $this->log(__FUNCTION__, $form_state);
    $form_state->set('stale', TRUE);
    $form_state->setRebuild();
  }

  /**
   * Button #ajax callback.
   */
  public function ajaxButton(array &$form, FormStateInterface $form_state): AjaxResponse {
    $this->log(__FUNCTION__, $form_state);
    return new AjaxResponse();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $this->log(__FUNCTION__, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->log(__FUNCTION__, $form_state);
  }

  /**
   * Logs a callback run on this object.
   */
  protected function log(string $callback, FormStateInterface $form_state): void {
    static::addLog($callback, $this->label, $form_state);
  }

  /**
   * Logs a callback.
   */
  protected static function addLog(string $callback, string $object_label, FormStateInterface $form_state): void {
    /** @var \Drupal\form_test\Form\FormTestFormObjectScopeForm $form_object */
    $form_object = $form_state->getFormObject();
    self::$log[] = $callback . ' ' . $object_label . '/' . $form_object->getLabel() . ($form_state instanceof SubformStateInterface ? ' (subform state)' : '');
    self::$values[$callback] = $form_state->getValue(['child', 'text']);
  }

}
