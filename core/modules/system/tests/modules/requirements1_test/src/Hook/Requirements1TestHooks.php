<?php

declare(strict_types=1);

namespace Drupal\requirements1_test\Hook;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for requirements1_test.
 */
class Requirements1TestHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_requirements_alter().
   *
   * @param array<string,\Drupal\Core\Extension\Requirement\Requirement> $requirements
   *   An array of requirements.
   */
  #[Hook('requirements_alter')]
  public function requirementsAlter(array &$requirements) : void {
    // Change the title.
    $requirements['requirements1_test_alterable']->setTitle($this->t('Requirements 1 Test - Changed'));
    // Decrease the severity.
    $requirements['requirements1_test_alterable']->setSeverity(RequirementSeverity::Warning);
    // Delete 'requirements1_test_deletable',
    unset($requirements['requirements1_test_deletable']);
  }

}
