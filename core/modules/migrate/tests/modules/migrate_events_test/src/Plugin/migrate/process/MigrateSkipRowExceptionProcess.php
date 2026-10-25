<?php

declare(strict_types=1);

namespace Drupal\migrate_events_test\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Process plugin to throw a MigrateSkipRowException.
 */
#[MigrateProcess(
  id: 'migrate_skip_row_exception',
  handle_multiples: FALSE,
)]
class MigrateSkipRowExceptionProcess extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): void {
    throw new MigrateSkipRowException();
  }

}
