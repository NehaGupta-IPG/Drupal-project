<?php

namespace Drupal\system\Plugin\SiteProperty;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\system\SitePropertyPluginBase;

/**
 * Plugin implementation of the site_property.
 *
 * @SiteProperty(
 *   id = "drupal_version",
 *   name = "drupal_version",
 *   site_bundles = {
 *     "Drupal\site\Entity\Bundle\DrupalSiteBundle"
 *   },
 *   label = @Translation("Drupal Version"),
 *   description = @Translation("The version of Drupal the site is running."),
 *   category = "environment"
 * )
 */
class DrupalVersion extends SitePropertyPluginBase {

  public function value() {
    return \Drupal::VERSION;
  }
}
