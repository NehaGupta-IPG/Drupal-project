<?php

namespace Drupal\system\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;

/**
 * Access check for cron routes.
 */
class CronAccessCheck implements AccessInterface {

  /**
   * Constructs a CronAccessCheck.
   *
   * @param \Psr\Log\LoggerInterface|null $logger
   *   A logger instance for the "cron" channel.
   * @param \Drupal\Core\State\StateInterface|null $state
   *   The state key/value store.
   */
  public function __construct(
    protected ?LoggerInterface $logger = NULL,
    protected ?StateInterface $state = NULL
  ) {
    if ($logger === NULL) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $logger argument is deprecated in drupal:10.3.0 and it will be required in drupal:11.0.0. See https://www.drupal.org/node/3409252', E_USER_DEPRECATED);
      $this->logger = \Drupal::logger('cron');
    }
    if ($state === NULL) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $state argument is deprecated in drupal:10.3.0 and it will be required in drupal:11.0.0. See https://www.drupal.org/node/3409252', E_USER_DEPRECATED);
      $this->state = \Drupal::state();
    }
  }

  /**
   * Checks access.
   *
   * @param string $key
   *   The cron key.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access($key) {
    if ($key !== $this->state->get('system.cron_key')) {
      $this->logger->notice('Cron could not run because an invalid key was used.');
      return AccessResult::forbidden()->setCacheMaxAge(0);
    }
    elseif ($this->state->get('system.maintenance_mode')) {
      $this->logger->notice('Cron could not run because the site is in maintenance mode.');
      return AccessResult::forbidden()->setCacheMaxAge(0);
    }
    return AccessResult::allowed()->setCacheMaxAge(0);
  }

}
