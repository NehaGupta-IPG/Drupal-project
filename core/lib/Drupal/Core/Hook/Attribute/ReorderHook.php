<?php

declare(strict_types=1);

namespace Drupal\Core\Hook\Attribute;

use Drupal\Core\Hook\Order\OrderInterface;

/**
 * Sets the order of an already existing implementation.
 *
 * The effect of this attribute is independent from the specific class or method
 * on which it is placed.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ReorderHook implements HookAttributeInterface {

  public function __construct(
    public string $hook,
    public string $class,
    public string $method,
    public OrderInterface $order,
  ) {}

}
