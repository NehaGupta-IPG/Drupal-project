<?php

namespace Drupal\sqlite3\Driver\Database\sqlite3\Install;

use Drupal\Core\Database\Database;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Database\DatabaseNotFoundException;
use Drupal\sqlite3\Driver\Database\sqlite3\Connection;
use Drupal\sqlite\Driver\Database\sqlite\Install\Tasks as BaseInstallTasks;

/**
 * Specifies installation tasks for SQLite3 driver.
 */
class Tasks extends BaseInstallTasks {

  /**
   * {@inheritdoc}
   */
  public function installable() {
    return extension_loaded('sqlite3');
  }

  /**
   * {@inheritdoc}
   */
  public function name() {
    return $this->t('SQLite3');
  }

}
