<?php

declare(strict_types=1);

namespace Drupal\js_sizzle_deprecation_test\Controller;

/**
 * Test controller that attaches the Sizzle deprecation test library.
 */
class JsSizzleDeprecationTestController {

  /**
   * Renders a page that attaches the Sizzle deprecation test library.
   *
   * @return array
   *   Render array.
   */
  public function test(): array {
    return [
      '#attached' => [
        'library' => [
          'js_sizzle_deprecation_test/sizzle_deprecation_test',
        ],
      ],
    ];
  }

}
