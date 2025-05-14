<?php

declare(strict_types=1);

namespace Drupal\Core\Hook\Attribute;

use Drupal\Core\Hook\Order\OrderInterface;

/**
 * Attribute to register a class method as a theme preprocess function.
 *
 * @see hook_preprocess()
 * @see hook_preprocess_HOOK()
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Preprocess extends Hook {

  /**
   * Constructor.
   *
   * @param string|null $theme_hook
   *   (optional) Name of a theme hook, or NULL for a generic preprocess that
   *   applies to all theme hooks.
   * @param string|null $module
   *   (optional) The module this implementation is for. This allows one module
   *   to implement a hook on behalf of another module. Defaults to the module
   *   the implementation is in.
   * @param \Drupal\Core\Hook\Order\OrderInterface|null $order
   *   (optional) Set the order of the implementation.
   */
  public function __construct(
    ?string $theme_hook = NULL,
    ?string $module = NULL,
    ?OrderInterface $order = NULL,
  ) {
    $hook = ($theme_hook === NULL) ? 'preprocess' : 'preprocess_' . $theme_hook;
    parent::__construct($hook, '', $module, $order);
  }

}
