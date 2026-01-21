<?php

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\sqlite\Driver\Database\sqlite\Select as SqliteSelect;

@trigger_error('\Drupal\Core\Database\Driver\sqlite\Select is deprecated in drupal:9.4.0 and is removed from drupal:11.0.0. The SQLite database driver has been moved to the sqlite module. See https://www.drupal.org/node/3129492', E_USER_DEPRECATED);

/**
 * SQLite implementation of \Drupal\Core\Database\Query\Select.
 *
 * @deprecated in drupal:9.4.0 and is removed from drupal:11.0.0. The SQLite
 *   database driver has been moved to the sqlite module.
 *
 * @see https://www.drupal.org/node/3129492
 */
class Select extends QuerySelect {

  /**
   * {@inheritdoc}
   */
  public function forUpdate($set = TRUE) {
    // SQLite does not support FOR UPDATE so nothing to do.
    return $this;
  }

}
