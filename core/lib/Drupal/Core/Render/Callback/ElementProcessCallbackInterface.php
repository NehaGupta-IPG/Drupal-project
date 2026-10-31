<?php

declare(strict_types=1);

namespace Drupal\Core\Render\Callback;

use Drupal\Core\Form\FormStateInterface;

/**
 * Callback that can be used in the '#process' part of a form element.
 *
 * @see \Drupal\Core\Form\FormBuilder::doBuildForm()
 */
interface ElementProcessCallbackInterface {

  /**
   * Processes a render element.
   *
   * Note that FormBuilder passes all parameters by reference for BC reasons,
   * but there is not really a good reason for it.
   *
   * @param array $element
   *   Original form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $complete_form
   *   Complete form.
   *
   * @return array
   *   Processed form element.
   */
  public function __invoke(array $element, FormStateInterface $form_state, array &$complete_form): array;

}
