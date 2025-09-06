<?php

namespace Drupal\update\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Cron hook implementations for the update module.
 */
class UpdateCronHooks {

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    $update_config = \Drupal::config('update.settings');
    $frequency = $update_config->get('check.interval_days');
    $interval = 60 * 60 * 24 * $frequency;
    $last_check = \Drupal::state()->get('update.last_check', 0);
    $request_time = \Drupal::time()->getRequestTime();
    if ($request_time - $last_check > $interval) {
      // If the configured update interval has elapsed, we want to invalidate
      // the data for all projects, attempt to re-fetch, and trigger any
      // configured notifications about the new status.
      update_refresh();
      update_fetch_data();
    }
    else {
      // Otherwise, see if any individual projects are now stale or still
      // missing data, and if so, try to fetch the data.
      update_get_available(TRUE);
    }
    $last_email_notice = \Drupal::state()->get('update.last_email_notification', 0);
    if ($request_time - $last_email_notice > $interval) {
      // If configured time between notifications elapsed, send email about
      // updates possibly available.
      \Drupal::moduleHandler()->loadInclude('update', 'inc', 'update.fetch');
      _update_cron_notify();
    }
  }

}
