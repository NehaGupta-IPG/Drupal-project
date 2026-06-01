<?php

namespace Drupal\system\Plugin\SiteProperty;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Url;
use Drupal\Core\Link;
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

  /**
   * {@inheritdoc}
   */
  public function value() {
    return \Drupal::state()->get('system.cron_last');
  }

  /**
   * {@inheritdoc}
   */
  public function view() {
    return [
      'ago' => [
        '#type' => 'container',
        '#markup' => t(":interval ago", [
          ':interval' => \Drupal::service('date.formatter')->formatInterval(time() - $this->value()),
        ]),  
      ],
      'run_cron' => Link::createFromRoute('Run Cron', 'system.run_cron', [], [
        'attributes' => [
          'class'=> [
            'button', 'button--small', 'button--primary',
          ]
        ]
      ])->toRenderable(),
      'configure' => Link::createFromRoute('Configure', 'system.cron_settings', [], [
        'attributes' => [
          'class'=> [
            'button', 'button--small', 'button--secondary',
          ]
        ]
      ])->toRenderable(),
    ];
  }
}
