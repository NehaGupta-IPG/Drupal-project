<?php

declare(strict_types=1);

namespace Drupal\Core\Form;

/**
 * Scopes the form object of a form state to an element's #form_object.
 *
 * Only getFormObject() and '::method' callbacks differ. Values stay absolute,
 * and setFormObject() acts on the decorated form state.
 *
 * @see \Drupal\Core\Render\Element\FormElementBase
 *
 * @ingroup form_api
 */
final class FormObjectScopedFormState extends FormStateDecoratorBase {

  /**
   * Constructs a new FormObjectScopedFormState.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state to decorate.
   * @param \Drupal\Core\Form\FormInterface $formObject
   *   The form object to scope the form state to.
   */
  public function __construct(FormStateInterface $form_state, protected readonly FormInterface $formObject) {
    $this->decoratedFormState = $form_state instanceof self ? $form_state->decoratedFormState : $form_state;
  }

  /**
   * Scopes a form state to the #form_object property of an element.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The scoped form state, or $form_state when the element sets no other
   *   form object.
   *
   * @throws \InvalidArgumentException
   *   Thrown when #form_object is not a form object.
   */
  public static function createForElement(array $element, FormStateInterface $form_state): FormStateInterface {
    $form_object = $element['#form_object'] ?? NULL;
    if ($form_object === NULL) {
      return $form_state;
    }
    if (!$form_object instanceof FormInterface) {
      throw new \InvalidArgumentException(sprintf('The #form_object property must implement %s, %s given.', FormInterface::class, get_debug_type($form_object)));
    }
    if ($form_object === $form_state->getFormObject()) {
      return $form_state;
    }
    return new self($form_state, $form_object);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormObject() {
    return $this->formObject;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareCallback($callback) {
    if (is_string($callback) && str_starts_with($callback, '::')) {
      return [$this->formObject, substr($callback, 2)];
    }
    return parent::prepareCallback($callback);
  }

}
