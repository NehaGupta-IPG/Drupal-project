<?php

declare(strict_types=1);

namespace Drupal\requirements_overview_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for requirements_overview_test.
 */
class RequirementsOverviewTestHooks {

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $count = \Drupal::state()->get('requirements_overview_test.count', 0);
    \Drupal::state()->set('requirements_overview_test.count', $count + 1);
    return [];
  }

}
