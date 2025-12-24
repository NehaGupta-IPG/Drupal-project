<?php

/**
 * @file
 * The PHP page that handles updating the Drupal installation.
 *
 * All Drupal code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt files in the "core" directory.
 */

use Drupal\Core\Update\UpdateKernel;

// This includes the autoloader and that loads bootstrap.inc which is needed
// to detect test runs.
$glue = require_once 'autoload_runtime.php';

// Disable garbage collection during test runs. Under certain circumstances the
// update path will create so many objects that garbage collection causes
// segmentation faults.
if (drupal_valid_test_ua()) {
  gc_collect_cycles();
  gc_disable();
}

$glue(new UpdateKernel($glue->appContext, $glue->autoloader, FALSE));
