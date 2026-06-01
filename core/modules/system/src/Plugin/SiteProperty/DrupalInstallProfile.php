<?php

namespace Drupal\system\Plugin\SiteProperty;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\system\SitePropertyPluginBase;

/**
 * Plugin implementation of the site_property.
 *
 * @SiteProperty(
 *   id = "drupal_install_profile",
 *   name = "drupal_install_profile",
 *   site_bundles = {
 *     "Drupal\site\Entity\Bundle\DrupalSiteBundle"
 *   },
 *   label = @Translation("Install profile"),
 *   description = @Translation("The install profile used to install the site."),
 *   category = "site"
 * )
 */
class DrupalInstallProfile extends SitePropertyPluginBase {

  public function value() {
    return \Drupal::service('config.factory')->get('core.extension')->get('profile');

  }

}
