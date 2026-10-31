<?php

declare(strict_types=1);

namespace Drupal\Core\Render\Callback;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;

/**
 * Callback that applies '#process' callbacks from the element type.
 */
class ElementTypeProcessCallback implements ElementProcessCallbackInterface {

  use DependencySerializationTrait;

  public function __construct(
    protected ElementInfoManagerInterface $elementInfoManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function __invoke(array $element, FormStateInterface $form_state, array &$complete_form): array {
    if (!isset($element['#type'])) {
      return $element;
    }
    $info = $this->elementInfoManager->getInfo($element['#type']);
    if (empty($info['#process'])) {
      // The element type does not exist, or it has no '#process' callbacks.
      return $element;
    }
    foreach ($info['#process'] as $callback) {
      // Replicate original behavior, where we get a new $complete_form
      // reference in each iteration.
      $form_state_complete_form = &$form_state->getCompleteForm();
      $element = call_user_func_array(
        $form_state->prepareCallback($callback),
        [&$element, &$form_state, &$form_state_complete_form],
      );
    }
    return $element;
  }

}
