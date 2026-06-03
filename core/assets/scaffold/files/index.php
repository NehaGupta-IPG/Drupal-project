<?php

/**
 * @file
 * The PHP page that serves all page requests on a Drupal installation.
 *
 * All Drupal code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt files in the "core" directory.
 */

use Drupal\Core\AppContextInterface;
use Drupal\Core\DrupalKernel;

require_once 'autoload_runtime.php';

return static function (AppContextInterface $app_context) {
  return new DrupalKernel('prod', require 'autoload.php', TRUE, $app_context);
};
