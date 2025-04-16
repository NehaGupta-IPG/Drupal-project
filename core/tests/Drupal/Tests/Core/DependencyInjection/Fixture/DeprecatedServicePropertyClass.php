<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\Fixture;

use Drupal\Core\DependencyInjection\DeprecatedServicePropertyTrait;

/**
 * Tests DeprecatedServicePropertyTrait.
 */
class DeprecatedServicePropertyClass {
  use DeprecatedServicePropertyTrait;

  /**
   * Deprecated service properties.
   *
   * @var string[]
   */
  protected $deprecatedProperties = [
    'foo' => 'bar',
  ];

}
