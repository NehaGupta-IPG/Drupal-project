<?php

namespace Drupal\Core\Composer\Dependencies;

use Drupal\Core\Composer\ProjectRootInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Composer\Semver\Semver;

/**
 * Builds hook_requirements() info about Composer dev dependencies.
 *
 * @internal
 */
class DevDependencyFinder {

  use StringTranslationTrait;

  /**
   * The Composer project root service.
   *
   * @var \Drupal\Core\Composer\ProjectRootInterface
   */
  protected $projectRoot;

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
  public function buildRequirements() {
    // Get a list of installed packages.
    $installed = $this->projectRoot->getInstalledPackages();
    // Get the dev requirements for the root project.
    $dev_requirements = $this->projectRoot->getDevRequirements();

    // Make a list of installed dev requirements.
    $installed_dev = array_intersect_key($installed, $dev_requirements);

    // If the number of dev requirements and installed dev requirements is
    // exactly the same, then we reason that the user probably issued a
    // 'composer install' command (defaulting to --dev). We then verify this by
    // checking core's dev requirement version constraints. If the version
    // constraints do not match, then either the user has an out-of-date vendor/
    // directory, or contrib has non-dev requirements on the same packages as
    // core's dev requirements, but in different versions. This is highly
    // unlikely, but still doesn't count as core dev requirements. This leaves
    // us with an edge case where contrib happens to require exactly the same
    // packages, with similar version constraints. In this case, it is
    // reasonable to issue a warning for the same reasons we'd issue the warning
    // for core's dev requirements. And we're unable to tell the difference
    // anyway.
    $dev_installed = FALSE;
    if ($installed_dev && (count($installed_dev) === count($dev_requirements))) {
      $dev_installed = TRUE;
      foreach ($dev_requirements as $package_name => $version_constraint) {
        // Special case for @dev packages, which can generate unparseable local
        // version numbers, such as [behat/mink] => 9999999-dev.
        if (strpos($version_constraint, 'dev') === FALSE) {
          if (!Semver::satisfies($installed_dev[$package_name], $version_constraint)) {
            $dev_installed = FALSE;
            break;
          }
        }
      }
    }

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
