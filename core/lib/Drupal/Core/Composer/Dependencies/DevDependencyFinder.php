<?php

namespace Drupal\Core\Composer\Dependencies;

use Drupal\Core\Composer\ProjectRootInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Builds hook_requirements() info about Composer dev dependencies.
 *
 * @internal
 */
class DevDependencyFinder {

  use StringTranslationTrait;

  /**
   * The Composer project root service.
   */
  protected ProjectRootInterface $projectRoot;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Composer\ProjectRootInterface $project_root
   *   The Composer project root service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(ProjectRootInterface $project_root, TranslationInterface $string_translation) {
    $this->projectRoot = $project_root;
    $this->setStringTranslation($string_translation);
  }

  /**
   * Builds the Composer dev warning for the Drupal installation.
   *
   * This warning will only reflect core's dev requirements.
   *
   * @return array[]
   *   Requirements array, suitable for hook_requirements().
   *
   * @see hook_requirements()
   */
  public function buildRequirements(): array {
    $dev_installed = $this->projectRoot->isDevInstall();

    $requirements = [
      'composer_dev_dependencies' => [
        'title' => $this->t('Composer dev requirements'),
        'description' => $this->t('This Drupal installation does not appear to have development packages installed. <a href=":documentation">Documentation on drupal.org</a>', [
          ':documentation' => 'https://www.drupal.org/docs/develop/using-composer',
        ]),
        'severity' => RequirementSeverity::OK,
      ],
    ];

    if ($dev_installed) {
      $requirements['composer_dev_dependencies']['description'] =
        $this->t('This Drupal installation appears to include development packages. This can lead to performance and security issues. Read the <a href=":documentation">documentation on drupal.org</a>', [
          ':documentation' => 'https://www.drupal.org/docs/develop/using-composer',
        ]);
      $requirements['composer_dev_dependencies']['severity'] = RequirementSeverity::Warning;
    }

    return $requirements;
  }

}
