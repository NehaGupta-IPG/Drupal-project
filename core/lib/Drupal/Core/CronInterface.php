<?php

namespace Drupal\Core;

/**
 * An interface for running cron tasks.
 *
 * @see https://www.drupal.org/docs/administering-a-drupal-site/cron-automated-tasks
 */
interface CronInterface {

  /**
   * Executes a cron run.
   *
   * This method performs several tasks:
   * - Ensures that the execution continues even if the request is cancelled.
   * - Switches the current user to an anonymous to ensure permissions.
   * - Attempts to acquire a cron lock to prevent parallel executions.
   * - With lock acquired, invokes handlers, run queues, and sets timestamp.
   * - Restores the original user session after the cron run.
   *
   * For PHPUnit tests, avoid calling this method simulating a cron run.
   * Instead, use appropriate methods or mock services to test functionality.
   *
   * @return bool
   *   TRUE upon successful cron execution, FALSE otherwise.
   */
  public function run();

}
