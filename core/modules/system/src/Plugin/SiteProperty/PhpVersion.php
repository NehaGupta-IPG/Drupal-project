<?php

namespace Drupal\system\Plugin\SiteProperty;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\system\SitePropertyPluginBase;
use Drupal\Core\Url;
use Drupal\Core\Utility\PhpRequirements;

/**
 * Plugin implementation of the site_property.
 *
 * @SiteProperty(
 *   id = "php_version",
 *   name = "php_version",
 *   label = @Translation("PHP Version"),
 *   site_bundles = {
 *     "Drupal\site\Entity\Bundle\PhpSiteBundle"
 *   },
 *   description = @Translation("The version of PHP the site is running."),
 *   category = "environment"
 * )
 */
class PhpVersion extends SitePropertyPluginBase {

  public function value() {
    return phpversion();
  }

  /**
   * {@inheritdoc}
   */
  public function view() {
    $memory_limit = ini_get('memory_limit');
    $build = [
      'version' => [
        '#type' => 'item',
        '#title' => t('Version'),
        '#markup' =>  $this->t('@phpversion (<a href=":url">more information</a>)', [
          '@phpversion' => $this->value(),
          ':url' => (new Url('system.php'))->toString(),
        ]),
      ],
      'memory_limit' => [
        '#type' => 'item',
        '#title' => t('Memory Limit'),
        '#markup' =>  $memory_limit == -1 ? t('-1 (Unlimited)') : $memory_limit,
      ],
    ];
    // Get the current default PHP requirements for this version of Drupal.
    // Get the current default PHP requirements for this version of Drupal.
    $php_version = $this->value();
    $minimum_supported_php = PhpRequirements::getMinimumSupportedPhp();

    // Check if the PHP version is below what Drupal supports.
    if (version_compare($php_version, $minimum_supported_php) < 0) {
      $build['description']['#markup'] = $this->t(
        'Your PHP installation is too old. Drupal requires at least PHP %version. It is recommended to upgrade to PHP version %recommended or higher for the best ongoing support. See <a href="http://php.net/supported-versions.php">PHP\'s version support documentation</a> and the <a href=":php_requirements">Drupal PHP requirements</a> page for more information.',
        [
          '%version' => $minimum_supported_php,
          '%recommended' => \Drupal::RECOMMENDED_PHP,
          ':php_requirements' => 'https://www.drupal.org/docs/system-requirements/php-requirements',
        ]
      );
    }
    // For PHP versions that are still supported but no longer recommended,
    // inform users of what's recommended, allowing them to take action before
    // it becomes urgent.
    elseif (version_compare($php_version, \Drupal::RECOMMENDED_PHP) < 0) {
      $build['description']['#markup'] = $this->t('It is recommended to upgrade to PHP version %recommended or higher for the best ongoing support.  See <a href="http://php.net/supported-versions.php">PHP\'s version support documentation</a> and the <a href=":php_requirements">Drupal PHP requirements</a> page for more information.',
        [
          '%recommended' => \Drupal::RECOMMENDED_PHP,
          ':php_requirements' => 'https://www.drupal.org/docs/system-requirements/php-requirements',
        ]
      );
    }
    return $build;
  }
}
