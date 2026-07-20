<?php

declare(strict_types=1);

namespace Drupal\deprecation_test;

/**
 * Defines a controller that calls a deprecated method.
 */
class DeprecatedController {

  /**
   * Controller callback.
   *
   * @return array
   *   Render array.
   */
  public function deprecatedMethod() {
    return [
      // @phpstan-ignore function.deprecated
      '#markup' => deprecation_test_function(),
    ];
  }

}
