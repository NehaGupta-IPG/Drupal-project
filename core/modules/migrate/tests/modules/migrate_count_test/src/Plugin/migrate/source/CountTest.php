<?php

declare(strict_types=1);

namespace Drupal\migrate_count_test\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\EmbeddedDataSource;
use Drupal\migrate\Row;

/**
 * Source plugin for testing migrate row and message counts.
 *
 * @MigrateSource(
 *   id = "count_test"
 * )
 */
class CountTest extends EmbeddedDataSource {

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    if ($row->get('data') === 'false') {
      return FALSE;
    }
    return parent::prepareRow($row);
  }

}
