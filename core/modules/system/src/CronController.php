<?php

namespace Drupal\system;

use Drupal\system\Controller\CronController as NewCronController;

// phpcs:ignore Drupal.Semantics.FunctionTriggerError
@trigger_error(__NAMESPACE__ . '\CronController is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use \Drupal\system\Controller\CronController instead. See https://www.drupal.org/node/3610540', E_USER_DEPRECATED);

/**
 * Controller for Cron handling.
 *
 * @deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use
 *   \Drupal\system\Controller\CronController instead.
 *
 * @see https://www.drupal.org/node/3610540
 */
class CronController extends NewCronController {

}
