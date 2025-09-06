<?php

namespace Drupal\update\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\update\UpdateManagerInterface;

/**
 * Cron hook implementations for the update module.
 */
class UpdateCronHooks {

  public function __construct(
    protected readonly StateInterface $state,
    protected readonly TimeInterface $time,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly MailManagerInterface $mailManager,
  ) {}

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    $frequency = $this->configFactory->get('update.settings')->get('check.interval_days');
    $interval = 60 * 60 * 24 * $frequency;
    $last_check = $this->state->get('update.last_check', 0);
    $request_time = $this->time->getRequestTime();
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
    $last_email_notice = $this->state->get('update.last_email_notification', 0);
    if ($request_time - $last_email_notice > $interval) {
      // If configured time between notifications elapsed, send email about
      // updates possibly available.
      $this->notify();
    }
  }

  /**
   * Performs any notifications that should be done once cron fetches new data.
   *
   * This method checks the status of the site using the new data and, depending
   * on the configuration of the site, notifies administrators via email if
   * there are new releases or missing security updates.
   *
   * @see update_requirements()
   */
  protected function notify(): void {
    $update_config = $this->configFactory->get('update.settings');
    $status = $this->moduleHandler->invoke('update', 'runtime_requirements');
    $params = [];
    $notify_all = ($update_config->get('notification.threshold') == 'all');
    foreach (['core', 'contrib'] as $report_type) {
      $type = 'update_' . $report_type;
      if (
        isset($status[$type]['severity'])
        && ($status[$type]['severity'] == RequirementSeverity::Error || ($notify_all && $status[$type]['reason'] == UpdateManagerInterface::NOT_CURRENT))
      ) {
        $params[$report_type] = $status[$type]['reason'];
      }
    }
    if (!empty($params)) {
      $notify_list = $update_config->get('notification.emails');
      if (!empty($notify_list)) {
        $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
        $request_time = $this->time->getRequestTime();
        foreach ($notify_list as $target) {
          if ($target_user = user_load_by_mail($target)) {
            $target_langcode = $target_user->getPreferredLangcode();
          }
          else {
            $target_langcode = $default_langcode;
          }
          $message = $this->mailManager->mail('update', 'status_notify', $target, $target_langcode, $params);
          // Track when the last mail was successfully sent to avoid sending
          // too many emails.
          if ($message['result']) {
            $this->state->set('update.last_email_notification', $request_time);
          }
        }
      }
    }
  }

}
