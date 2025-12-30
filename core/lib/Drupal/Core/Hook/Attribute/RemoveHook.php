<?php

declare(strict_types=1);

namespace Drupal\Core\Hook\Attribute;

/**
 * Removes an already existing implementation.
 *
 * The effect of this attribute is independent from the specific class or method
 * on which it is placed.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class RemoveHook implements HookAttributeInterface {

  public function __construct(
    public readonly string $hook,
    public readonly string $class,
    public readonly string $method,
  ) {}

}
