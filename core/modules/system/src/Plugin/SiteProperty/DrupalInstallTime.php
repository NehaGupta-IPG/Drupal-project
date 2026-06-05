<?php

namespace Drupal\system\Plugin\SiteProperty;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\system\SitePropertyPluginBase;
use Drupal\Component\Datetime\time;

/**
 * Plugin implementation of the site_property.
 *
 * @SiteProperty(
 *   id = "drupal_install_time",
 *   name = "drupal_install_time",
 *   site_bundles = {
 *     "Drupal\site\Entity\Bundle\DrupalSiteBundle"
 *   },
 *   label = @Translation("Install time"),
 *   description = @Translation("The date the site was originally installed."),
 *   category = "site"
 * )
 */
class DrupalInstallTime extends SitePropertyPluginBase {

/**
   * {@inheritdoc}
   */ 
  public function value() {
    return \Drupal::state()->get('install_time');
  }

  /**
   * {@inheritdoc}
   */
  public function view() {
    return [
      'date' => [
        '#type' => 'container',
        '#markup' => \Drupal::service('date.formatter')->format($this->value()),
      ],
      'ago' => [
        '#type' => 'container',
        '#markup' => t(":interval ago", [
          ':interval' => \Drupal::service('date.formatter')->formatInterval(time() - $this->value()),
        ]),  
      ],
    ];
  }
}
