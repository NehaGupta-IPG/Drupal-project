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
   * - Switches the current user to an anonymous user to ensure consistent permissions.
   * - Attempts to acquire a cron lock to prevent parallel executions.
   * - If the lock is acquired, invokes cron handlers, processes queues, and sets the last cron run timestamp.
   * - Restores the original user session after the cron run.
   *
   * For PHPUnit tests, avoid directly calling this method to simulate a cron run.
   * Instead, use appropriate methods or mock the necessary services to test cron functionality.
   *
   * @return bool
   *   TRUE upon successful cron execution, FALSE otherwise.
   */
  public function run();

}
