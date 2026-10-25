<?php

declare(strict_types=1);

namespace Drupal\migrate_events_test\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Process plugin to throw a MigrateException.
 */
#[MigrateProcess(
  id: 'migrate_exception_process',
  handle_multiples: FALSE,
)]
class MigrateExceptionProcess extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): void {
    throw new MigrateException();
  }

}
