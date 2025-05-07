<?php

declare(strict_types=1);

namespace Drupal\Core\Hook\Attribute;

use Drupal\Core\Hook\Order\OrderInterface;

/**
 * Attribute for defining a class method as a preprocess function.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Preprocess extends Hook {

  /**
   * Constructor.
   *
   * @param string|null $theme_hook
   *   (optional) Name of a theme hook, or NULL for a generic preprocess that
   *   applies to all theme hooks.
   * @param string $method
   *   (optional) The method name. If this attribute is on a method, this
   *   parameter is not required. If this attribute is on a class and this
   *   parameter is omitted, the class must have an __invoke() method, which is
   *   taken as the hook implementation.
   * @param string|null $module
   *   (optional) The module this implementation is for. This allows one module
   *   to implement a hook on behalf of another module. Defaults to the module
   *   the implementation is in.
   * @param \Drupal\Core\Hook\Order\OrderInterface|null $order
   *   (optional) Set the order of the implementation.
   */
  public function __construct(
    ?string $theme_hook = NULL,
    string $method = '',
    ?string $module = NULL,
    ?OrderInterface $order = NULL,
  ) {
    $hook = ($theme_hook === NULL) ? 'preprocess' : 'preprocess_' . $theme_hook;
    parent::__construct($hook, $method, $module, $order);
  }

}
