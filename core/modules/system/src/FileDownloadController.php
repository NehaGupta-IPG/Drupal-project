<?php

namespace Drupal\system;

use Drupal\system\Controller\FileDownloadController as NewFileDownloadController;

// phpcs:ignore Drupal.Semantics.FunctionTriggerError
@trigger_error(__NAMESPACE__ . '\FileDownloadController is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use \Drupal\system\Controller\FileDownloadController instead. See https://www.drupal.org/node/3610540', E_USER_DEPRECATED);

/**
 * System file controller.
 *
 * @deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use
 *   \Drupal\system\Controller\FileDownloadController instead.
 *
 * @see https://www.drupal.org/node/3610540
 */
class FileDownloadController extends NewFileDownloadController {

}
