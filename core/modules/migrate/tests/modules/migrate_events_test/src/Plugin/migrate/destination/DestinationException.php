<?php

declare(strict_types=1);

namespace Drupal\migrate_events_test\Plugin\migrate\destination;

use Drupal\migrate\Attribute\MigrateDestination;
use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Drupal\migrate\Row;

/**
 * Destination plugin that throws an Exception on import.
 */
#[MigrateDestination(
  id: 'destination_exception',
  requirements_met: TRUE,
)]
class DestinationException extends DestinationBase {

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    $ids['value']['type'] = 'string';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return ['value' => 'Dummy value'];
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []): void {
    throw new \Exception();
  }

}
