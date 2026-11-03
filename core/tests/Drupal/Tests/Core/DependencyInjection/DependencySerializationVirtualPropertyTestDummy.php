<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Defines a test class with a virtual property, which only has a get hook.
 *
 * phpcs:ignoreFile
 */
class DependencySerializationVirtualPropertyTestDummy {

  use DependencySerializationTrait;

  /**
   * The number of times the get hook of $virtual ran.
   */
  public int $hookCalls = 0;

  /**
   * A property with a stored value.
   */
  public string $stored = 'stored value';

  /**
   * A virtual property, which has no stored value.
   */
  public string $virtual {
    get {
      $this->hookCalls++;
      return 'virtual value';
    }
  }

}
