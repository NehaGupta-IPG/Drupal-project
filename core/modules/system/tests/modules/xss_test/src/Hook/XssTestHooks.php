<?php

declare(strict_types=1);

namespace Drupal\xss_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for xss_test.
 */
class XssTestHooks {

  /**
   * Implements hook_xss_disallowed_attributes().
   */
  #[Hook('xss_disallowed_attributes')]
  public function xssDisallowedAttributes(): array {
    return ['x-data', 'x-on*'];
  }

}
