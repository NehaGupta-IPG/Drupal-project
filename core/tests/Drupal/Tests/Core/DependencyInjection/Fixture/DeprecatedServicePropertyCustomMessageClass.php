<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\Fixture;

use Drupal\Core\DependencyInjection\DeprecatedServicePropertyTrait;

/**
 * Tests DeprecatedServicePropertyTrait.
 */
class DeprecatedServicePropertyCustomMessageClass {
  use DeprecatedServicePropertyTrait;

  /**
   * Deprecated service properties.
   *
   * @var string[]
   */
  protected $deprecatedProperties = [
    'foo' => [
      'service' => 'bar',
      'message' => 'Custom message',
    ],
  ];

}
