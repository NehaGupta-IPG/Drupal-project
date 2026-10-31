<?php

declare(strict_types=1);

namespace Drupal\Core\Render\Callback;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Render\Element\RenderCallbackInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Security\DoTrustedCallbackTrait;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Utility\CallableResolver;

/**
 * Callback that applies '#process' callbacks from the element type.
 */
class ElementTypePreRenderCallback implements ElementPreRenderCallbackInterface {

  use DependencySerializationTrait;
  use DoTrustedCallbackTrait;

  public function __construct(
    protected ElementInfoManagerInterface $elementInfoManager,
    protected CallableResolver $callableResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function __invoke(array $element): array {
    if (!isset($element['#type'])) {
      return $element;
    }
    $info = $this->elementInfoManager->getInfo($element['#type']);
    if (empty($info['#pre_render'])) {
      // The element type does not exist, or it has no '#pre_render' callbacks.
      return $element;
    }
    foreach ($info['#pre_render'] as $callable) {
      $element = $this->doCallback('#pre_render', $callable, [$element]);
    }
    return $element;
  }

  /**
   * Performs a callback.
   *
   * @param string $callback_type
   *   The type of the callback. For example, '#post_render'.
   * @param string|callable $callback
   *   The callback to perform.
   * @param array $args
   *   The arguments to pass to the callback.
   *
   * @return mixed
   *   The callback's return value.
   *
   * @see \Drupal\Core\Render\Renderer::doCallback()
   */
  protected function doCallback($callback_type, $callback, array $args) {
    $callable = $this->callableResolver->getCallableFromDefinition($callback);
    $message = sprintf('Render %s callbacks must be methods of a class that implements \Drupal\Core\Security\TrustedCallbackInterface or be an anonymous function. The callback was %s. See https://www.drupal.org/node/2966725', $callback_type, '%s');
    // Add \Drupal\Core\Render\Element\RenderCallbackInterface as an extra
    // trusted interface so that:
    // - All public methods on Render elements are considered trusted.
    // - Helper classes that contain only callback methods can implement this
    //   instead of TrustedCallbackInterface.
    return $this->doTrustedCallback($callable, $args, $message, TrustedCallbackInterface::THROW_EXCEPTION, RenderCallbackInterface::class);
  }

}
