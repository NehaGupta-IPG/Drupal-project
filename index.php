<?php

/**
 * @file
 * The PHP page that serves all page requests on a Drupal installation.
 *
 * All Drupal code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt files in the "core" directory.
 */

use Drupal\Core\DrupalKernel;

$glue = require_once 'autoload_runtime.php';
$glue(new DrupalKernel($glue->appContext, $glue->autoloader));
