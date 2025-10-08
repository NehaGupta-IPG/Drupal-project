<?php

declare(strict_types=1);

namespace Drupal\module_autoload_test;

/**
 * Class for testing module autoloading.
 */
class SomeClass {

  const TEST = '\Drupal\module_autoload_test\SomeClass::TEST';

  public function testMethod(): string {
    return 'Drupal\\module_autoload_test\\SomeClass::testMethod() was invoked.';
  }

}
