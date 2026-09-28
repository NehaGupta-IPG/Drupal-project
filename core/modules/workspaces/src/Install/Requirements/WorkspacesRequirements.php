<?php

declare(strict_types=1);

namespace Drupal\workspaces\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;

/**
 * Install time requirements for the workspaces module.
 *
 * @phpstan-import-type RequirementInfo from \Drupal\Core\Extension\InstallRequirementsInterface
 */
class WorkspacesRequirements implements InstallRequirementsInterface {

  /**
   * Check installation requirements.
   *
   * @return array{'workspace_incompatibility'?: RequirementInfo}
   *   The module's requirements.
   *
   * @see \Drupal\Core\Extension\InstallRequirementsInterface::getRequirements()
   */
  public static function getRequirements(): array {
    $requirements = [];
    if (\Drupal::moduleHandler()->moduleExists('workspace')) {
      $requirements['workspace_incompatibility'] = [
        'severity' => RequirementSeverity::Error,
        'description' => t('Workspaces can not be installed when the contributed Workspace module is also installed. See the <a href=":link">upgrade path</a> page for more information on how to upgrade.', [
          ':link' => 'https://www.drupal.org/node/2987783',
        ]),
      ];
    }

    return $requirements;
  }

}
