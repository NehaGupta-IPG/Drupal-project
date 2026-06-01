<?php

namespace Drupal\system\Plugin\SiteProperty;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\system\SitePropertyPluginBase;

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
    return [
      'version' => [
        '#type' => 'item',
        '#title' => t('Version'),
        '#markup' => $this->value(),
      ],
      'memory_limit' => [
        '#type' => 'item',
        '#title' => t('Memory Limit'),
        '#markup' =>  $memory_limit == -1 ? t('-1 (Unlimited)') : $memory_limit,
      ],
    ];
  }
}
