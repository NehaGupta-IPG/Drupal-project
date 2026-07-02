<?php

declare(strict_types=1);

namespace Drupal\driver_test\Driver\Database\DriverTestPgsql\Install;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\pgsql\Driver\Database\pgsql\Install\Tasks as CoreTasks;

/**
 * Specifies installation tasks for PostgreSQL databases.
 */
class Tasks extends CoreTasks {

  /**
   * {@inheritdoc}
   */
  public function name(): TranslatableMarkup {
    return $this->t('PostgreSQL by the driver_test module');
  }

}
