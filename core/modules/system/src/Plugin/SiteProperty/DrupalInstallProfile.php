<?php

namespace Drupal\system\Plugin\SiteProperty;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\system\SitePropertyPluginBase;
use Drupal\Core\Site\Settings;

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

  public function view() {
    $build['install_profile']['#markup'] = $this->value();
    $settings = Settings::getAll();
    if (array_key_exists('install_profile', $settings)) {
      // The following message is only informational because not all site
      // owners have access to edit their settings.php as it may be
      // controlled by their hosting provider.
      $$build['install_profile_in_settings'] = [
        '#type' => 'item',
        '#title' => $this->t('Install profile in settings'),
        '#description' => $this->t("Drupal 9 no longer uses the \$settings['install_profile'] value in settings.php and it should be removed."),
      ];
    }
    return $build;
  }

}
