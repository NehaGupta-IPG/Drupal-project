<?php

declare(strict_types=1);

namespace Drupal\requirements_overview_error_test\Hook;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for requirements_overview_error_test.
 */
class RequirementsOverviewErrorTestHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    return [
      'requirements_overview_error_test' => [
        'title' => $this->t('Requirements overview error test'),
        'severity' => RequirementSeverity::Error,
        'description' => $this->t('Simulated error for testing.'),
      ],
    ];
  }

}
