<?php

namespace Drupal\system\Plugin\SiteProperty;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Url;
use Drupal\system\SitePropertyPluginBase;

/**
 * Plugin implementation of the site_property.
 *
 * @SiteProperty(
 *   id = "drupal_cron_last",
 *   name = "drupal_cron_last",
 *   label = @Translation("Last cron run"),
 *   description = @Translation("The last time cron was run on the site."),
 *   category = "environment"
 * )
 */
class DrupalCronLast extends SitePropertyPluginBase {
  public function value() {
    return \Drupal::state()->get('system.cron_last');
  }

  public function view() {
    return [
      '#type' => 'item',
      '#title' => t('Last cron run'),
      '#markup' => \Drupal::service('date.formatter')->format($this->value()),
      '#description' => t(":interval ago", [
        ':interval' => \Drupal::service('date.formatter')->formatInterval(time() - $this->value()),
      ]),
      '#description_display' => 'after'
    ];
  }
}
