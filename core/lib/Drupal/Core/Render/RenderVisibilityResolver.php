<?php

namespace Drupal\Core\Render;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Security\DoTrustedCallbackTrait;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Utility\CallableResolver;

/**
 * Resolves visibility of render arrays without full rendering.
 *
 * Provides a lightweight mechanism to evaluate #access_callback on render
 * arrays without triggering #lazy_builder, #pre_render, or #post_render
 * callbacks. This allows templates to determine region visibility without
 * forcing a full render, preserving BigPipe and Dynamic Page Cache behavior.
 *
 * @see \Drupal\Core\Render\Renderer
 * @see \Drupal\Core\Template\TwigExtension
 */
class RenderVisibilityResolver {

  use DoTrustedCallbackTrait;

  /**
   * Constructs a RenderVisibilityResolver.
   *
   * @param \Drupal\Core\Utility\CallableResolver $callableResolver
   *   The callable resolver for resolving callback definitions.
   */
  public function __construct(
    protected CallableResolver $callableResolver,
  ) {
  }

  /**
   * Recursively evaluates #access_callback on a render array tree.
   *
   * Only evaluates #access_callback. Does NOT invoke #lazy_builder,
   * #pre_render, or #post_render. This preserves deferred rendering
   * contracts and avoids breaking BigPipe or Dynamic Page Cache.
   *
   * @param array &$elements
   *   The render array to evaluate. Passed by reference so that #access
   *   properties can be set on elements and children.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The combined access result with merged cacheability metadata.
   */
  public function resolveAccess(array &$elements): AccessResultInterface {
    // Empty render arrays are not visible.
    if (empty($elements)) {
      return AccessResult::forbidden('empty render array');
    }

    // Elements with non-visible #type are not visible.
    if (isset($elements['#type']) && in_array($elements['#type'], ['value', 'hidden', 'token'], TRUE)) {
      return AccessResult::forbidden('non-visible element type: ' . $elements['#type']);
    }

    // If #access is already set, use it directly.
    if (isset($elements['#access'])) {
      return $this->normalizeAccessResult($elements['#access']);
    }

    // If #access_callback is set, evaluate it.
    if (isset($elements['#access_callback'])) {
      $access_result = $this->evaluateAccessCallback($elements);
      $elements['#access'] = $access_result;
      return $access_result;
    }

    // If #lazy_builder is set but no #access, treat as potentially visible.
    // Do NOT invoke the lazy builder — that would break deferred rendering.
    if (isset($elements['#lazy_builder'])) {
      return AccessResult::allowed();
    }

    // Recursively evaluate children.
    return $this->resolveChildrenAccess($elements);
  }

  /**
   * Checks if a render array will produce visible output.
   *
   * @param array $elements
   *   The render array to check.
   *
   * @return bool
   *   TRUE if the element is accessible and will produce output.
   */
  public function isVisible(array $elements): bool {
    return $this->resolveAccess($elements)->isAllowed();
  }

  /**
   * Evaluates a #access_callback on an element.
   *
   * @param array &$elements
   *   The element whose callback to evaluate.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result with cacheability metadata.
   */
  protected function evaluateAccessCallback(array &$elements): AccessResultInterface {
    $callable = $this->callableResolver->getCallableFromDefinition($elements['#access_callback']);

    $message = 'Render #access_callback callbacks must be methods of a class that implements \Drupal\Core\Security\TrustedCallbackInterface or be an anonymous function. The callback was %s. See https://www.drupal.org/node/2966725';
    $result = $this->doTrustedCallback($callable, [$elements], $message, TrustedCallbackInterface::THROW_EXCEPTION, RenderCallbackInterface::class);

    // Normalize the result to an AccessResultInterface.
    if ($result instanceof AccessResultInterface) {
      return $result;
    }
    if (is_bool($result)) {
      return $result ? AccessResult::allowed() : AccessResult::forbidden();
    }
    // Fallback for unexpected return types.
    return AccessResult::forbidden('unexpected #access_callback return type');
  }

  /**
   * Recursively evaluates access on child elements.
   *
   * @param array &$elements
   *   The parent element whose children to evaluate.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The combined access result. Returns allowed if any child is visible.
   */
  protected function resolveChildrenAccess(array &$elements): AccessResultInterface {
    $children = Element::children($elements);

    if (empty($children)) {
      // No children — check if there's #markup or #plain_text content.
      if (!empty($elements['#markup']) || !empty($elements['#plain_text'])) {
        return AccessResult::allowed();
      }
      return AccessResult::forbidden('no children or content');
    }

    $combined = NULL;
    foreach ($children as $key) {
      if (!isset($elements[$key]) || !is_array($elements[$key])) {
        continue;
      }
      $child_result = $this->resolveAccess($elements[$key]);
      if ($combined === NULL) {
        $combined = $child_result;
      }
      else {
        // For region visibility, we want "at least one child visible".
        // This is different from access control where forbidden is contagious.
        // If either result is allowed, the combined result is allowed.
        if ($child_result->isAllowed() || $combined->isAllowed()) {
          $combined = AccessResult::allowed();
        }
        elseif ($child_result->isForbidden() || $combined->isForbidden()) {
          $combined = AccessResult::forbidden();
        }
        else {
          $combined = AccessResult::neutral();
        }
      }
    }

    return $combined ?? AccessResult::forbidden('no valid children');
  }

  /**
   * Normalizes a #access value to an AccessResultInterface.
   *
   * @param mixed $access
   *   The #access value. Can be a boolean or AccessResultInterface.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The normalized access result.
   */
  protected function normalizeAccessResult($access): AccessResultInterface {
    if ($access instanceof AccessResultInterface) {
      return $access;
    }
    return $access ? AccessResult::allowed() : AccessResult::forbidden();
  }

}
