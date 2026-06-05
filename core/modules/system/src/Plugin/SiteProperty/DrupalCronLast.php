<?php

namespace Drupal\system\Plugin\SiteProperty;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Url;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Link;
use Drupal\system\SitePropertyPluginBase;
use Drupal\system\Install\Requirements\SystemRequirements;

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
      $build = [];

      $cron_config = \Drupal::config('system.cron');
      // Cron warning threshold defaults to two days.
      $threshold_warning = $cron_config->get('threshold.requirements_warning');
      // Cron error threshold defaults to two weeks.
      $threshold_error = $cron_config->get('threshold.requirements_error');

      // Determine when cron last ran.
      $cron_last = \Drupal::state()->get('system.cron_last');
      if (!is_numeric($cron_last)) {
        $cron_last = \Drupal::state()->get('install_time', 0);
      }

      // Determine severity based on time since cron last ran.
      $severity = RequirementSeverity::Info;
      $request_time = \Drupal::time()->getRequestTime();
      if ($request_time - $cron_last > $threshold_error) {
        $severity = RequirementSeverity::Error;
      }
      elseif ($request_time - $cron_last > $threshold_warning) {
        $severity = RequirementSeverity::Warning;
      }

      // Set summary and description based on values determined above.
      $summary = $this->t('Last run @time ago', ['@time' => \Drupal::service('date.formatter')->formatTimeDiffSince($cron_last)]);
      $build['description'][] = [
        '#markup' => $summary,
      ];
      if ($severity != RequirementSeverity::Info) {
        $build['description'][] = [
          [
            '#markup' => $this->t('Cron has not run recently.'),
            '#suffix' => ' ',
          ],
          [
            '#markup' => $this->t('For more information, see the online handbook entry for <a href=":cron-handbook">configuring cron jobs</a>.', [':cron-handbook' => 'https://www.drupal.org/docs/administering-a-drupal-site/cron-automated-tasks/cron-automated-tasks-overview']),
            '#suffix' => ' ',
          ],
        ];
      }
      $build['description'][] = [
        [
          '#type' => 'link',
          '#prefix' => '(',
          '#title' => $this->t('more information'),
          '#suffix' => ')',
          '#url' => Url::fromRoute('system.cron_settings'),
        ],
        [
          '#prefix' => '<span class="cron-description__run-cron">',
          '#suffix' => '</span>',
          '#type' => 'link',
          '#title' => $this->t('Run cron'),
          '#url' => Url::fromRoute('system.run_cron'),
        ],
      ];
    // $build = [
    //   'ago' => [
    //     '#type' => 'container',
    //     '#markup' => t(":interval ago", [
    //       ':interval' => \Drupal::service('date.formatter')->formatInterval(time() - $this->value()),
    //     ]),  
    //   ],
    //   'run_cron' => Link::createFromRoute('Run Cron', 'system.run_cron', [], [
    //     'attributes' => [
    //       'class'=> [
    //         'button', 'button--small', 'button--primary',
    //       ]
    //     ]
    //   ])->toRenderable(),
    //   'configure' => Link::createFromRoute('Configure', 'system.cron_settings', [], [
    //     'attributes' => [
    //       'class'=> [
    //         'button', 'button--small', 'button--secondary',
    //       ]
    //     ]
    //   ])->toRenderable(),
    // ];
    return $build;
  }
}
