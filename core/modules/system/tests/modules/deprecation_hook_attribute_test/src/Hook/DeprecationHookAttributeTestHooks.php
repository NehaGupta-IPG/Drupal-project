<?php

declare(strict_types=1);

namespace Drupal\deprecation_hook_attribute_test\Hook;

use Drupal\Core\Hook\Attribute\DeprecatedHook;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Implements hooks for the deprecation hook attribute test.
 */
#[DeprecatedHook('deprecated_hook', 'deprecation_test', 'x.x.x', 'y.y.y', 'Use something else.', 'change record')]
#[DeprecatedHook('deprecated_alter', 'deprecation_test', 'x.x.x', 'y.y.y', 'Use something else.', 'change record')]
#[DeprecatedHook('unimplemented_hook_name', 'deprecation_test', 'x.x.x', 'y.y.y', 'Use something else.', 'change record')]
class DeprecationHookAttributeTestHooks {

  /**
   * Implements hook_deprecated_hook().
   */
  #[Hook('deprecated_hook')]
  public function deprecatedHook($arg): mixed {
    return $arg;
  }

  /**
   * Implements hook_deprecated_alter_alter().
   */
  #[Hook('deprecated_alter_alter')]
  public function deprecatedAlterAlterFirst(&$data, $context1, $context2): void {
    $data = [$context1, $context2];
  }

}
