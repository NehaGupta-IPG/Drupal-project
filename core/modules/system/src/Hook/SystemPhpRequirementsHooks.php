<?php

namespace Drupal\system\Hook;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Utility\PhpRequirements;

/**
 * PHP requirements hook implementations for system module.
 */
class SystemPhpRequirementsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements', order: Order::First)]
  public function phpRuntimeRequirements(): array {
    return $this->phpRequirements(RequirementSeverity::Error);
  }

  /**
   * Implements hook_update_requirements().
   */
  #[Hook('update_requirements', order: Order::First)]
  public function phpUpdateequirements(): array {
    return $this->phpRequirements(RequirementSeverity::Warning);
  }

  /**
   * Get the phpRequirements for drupal.
   *
   * @param \Drupal\Core\Extension\Requirement\RequirementSeverity $fallbackSeverity
   *   The severity of the requirement.
   *
   * @return array
   *   Current requirements array.
   */
  public function phpRequirements(RequirementSeverity $fallbackSeverity): array {
    $requirements = [];
    $phpversion = phpversion();
    $phpversion_label = $this->t('@phpversion (<a href=":url">more information</a>)', [
      '@phpversion' => $phpversion,
      ':url' => (new Url('system.php'))->toString(),
    ]);
    $requirements['php'] = [
      'title' => $this->t('PHP'),
      'value' => $phpversion_label,
    ];
    $this->minimumPhp($phpversion, $requirements, $fallbackSeverity);
    if (!isset($requirements['php']['severity'])) {
      $this->recommendedPhp($phpversion, $requirements);
    }

    return $requirements;
  }

  /**
   * Check if environment meets minimum php version requirement.
   *
   * @param string $phpversion
   *   The php version to check.
   * @param array[] $requirements
   *   The requirements array as specified in hook_*_requirements().
   * @param \Drupal\Core\Extension\Requirement\RequirementSeverity $fallbackSeverity
   *   Default severity to use if minimum is not met.
   */
  protected function minimumPhp(string $phpversion, array &$requirements, RequirementSeverity $fallbackSeverity): void {
    // Verify the user is running a supported PHP version.
    // If the site is running a recommended version of PHP, just display it
    // as an informational message on the status report. This will be overridden
    // with an error or warning if the site is running older PHP versions for
    // which Drupal has already or will soon drop support.

    // Check if the PHP version is below what Drupal supports.
    if (version_compare($phpversion, PhpRequirements::getMinimumSupportedPhp()) < 0) {
      $requirements['php']['description'] = $this->t(
        'Your PHP installation is too old. Drupal requires at least PHP %version. It is recommended to upgrade to PHP version %recommended or higher for the best ongoing support. See <a href="http://php.net/supported-versions.php">PHP\'s version support documentation</a> and the <a href=":php_requirements">Drupal PHP requirements</a> page for more information.',
        [
          '%version' => PhpRequirements::getMinimumSupportedPhp(),
          '%recommended' => \Drupal::RECOMMENDED_PHP,
          ':php_requirements' => 'https://www.drupal.org/docs/system-requirements/php-requirements',
        ]
      );

      // If the PHP version is also below the absolute minimum allowed, it's not
      // safe to continue with the requirements check, and should always be an
      // error.
      if (version_compare($phpversion, \Drupal::MINIMUM_PHP) < 0) {
        $requirements['php']['severity'] = RequirementSeverity::Error;
      }
      else {
        // Otherwise, the message depends on the fallback severity
        // - RequirementSeverity::Error during runtime
        // - RequirementSeverity::Warning during update
        $requirements['php']['severity'] = $fallbackSeverity;
      }
    }
  }

  /**
   * Check if environment meets recommended php version requirement.
   *
   * @param string $phpversion
   *   The php version to check.
   * @param array[] $requirements
   *   The requirements array as specified in hook_*_requirements().
   */
  protected function recommendedPhp(string $phpversion, array &$requirements): void {
    // For PHP versions that are still supported but no longer recommended,
    // inform users of what's recommended, allowing them to take action before
    // it becomes urgent.
    if (version_compare($phpversion, \Drupal::RECOMMENDED_PHP) < 0) {
      $requirements['php']['description'] = $this->t(
        'It is recommended to upgrade to PHP version %recommended or higher for the best ongoing support.  See <a href="http://php.net/supported-versions.php">PHP\'s version support documentation</a> and the <a href=":php_requirements">Drupal PHP requirements</a> page for more information.',
        [
          '%recommended' => \Drupal::RECOMMENDED_PHP,
          ':php_requirements' => 'https://www.drupal.org/docs/system-requirements/php-requirements',
        ]
      );
      $requirements['php']['severity'] = RequirementSeverity::Info;
    }
  }

}
