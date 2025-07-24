<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Attribute;

/**
 * Attribute class to add a route provider to an entity type definition.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class RouteProviderHandler extends EntityTypeProperty {

  /**
   * Constructs a RouteProviderHandler attribute.
   *
   * @param class-string $class
   *   The route provider class name.
   * @param string $group
   *   The route provider group, such as 'html', 'permissions', 'revision', etc.
   *   Defaults to 'html'.
   */
  public function __construct(
    public readonly string $class,
    public readonly string $group = 'html',
  ) {
    parent::__construct(['handlers', 'route_provider', $group], $class);
  }

}
