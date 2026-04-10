<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate\Kernel;

use Drupal\migrate_high_water_test\Plugin\migrate\source\HighWaterTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\Database\Connection;
use Drupal\migrate\Plugin\migrate\id_map\Sql;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Prophecy\Argument;

/**
 * Tests the high water handling.
 */
#[CoversClass(HighWaterTest::class)]
#[Group('migrate')]
#[RunTestsInSeparateProcesses]
class HighWaterNotJoinableTest extends MigrateSqlSourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate_high_water_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    // Ensure that \Drupal\migrate\Plugin\migrate\source\SqlBase::mapJoinable()
    // considers the ID map plugin not joinable.
    $connection = $this->prophesize(Connection::class);
    $connection->getConnectionOptions()->willReturn([
      'driver' => 'foo',
      'database' => 'bar',
    ]);

    $id_map = $this->prophesize(Sql::class);
    $id_map->getDatabase()->willReturn(
      $connection->reveal()
    );
    // Pretend the row with source ID 1 has already been imported; this is
    // reflected in the high water mark. We consider none of the other rows
    // imported, so those will return the empty array.
    $id_map->getRowBySource(Argument::exact(['id' => '1']))->willReturn([
      'source_row_status' => MigrateIdMapInterface::STATUS_IMPORTED,
    ]);
    $id_map->getRowBySource(Argument::any())->willReturn([]);
    // ::saveIdMapping() gets called when MigrateSourceTestBase::testSource()
    // re-iterates over all rows while intentionally triggering a skip exception
    // using migrate_skip_all_rows_test_migrate_prepare_row().
    $id_map->saveIdMapping(
      Argument::any(),
      Argument::any(),
      Argument::exact(MigrateIdMapInterface::STATUS_IGNORED)
    )->willReturn();
    $this->migration->getIdMap()->willReturn(
      $id_map->reveal()
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function providerSource() {
    $tests = [];

    // Test high water when the map is not joinable.
    // The source data.
    $tests[0]['source_data']['high_water_node'] = [
      [
        'id' => 1,
        'title' => 'Item 1',
        'changed' => 1,
      ],
      [
        'id' => 2,
        'title' => 'Item 2',
        'changed' => 2,
      ],
      [
        'id' => 3,
        'title' => 'Item 3',
        'changed' => 3,
      ],
    ];

    // The expected results.
    $tests[0]['expected_data'] = [
      [
        'id' => 2,
        'title' => 'Item 2',
        'changed' => 2,
      ],
      [
        'id' => 3,
        'title' => 'Item 3',
        'changed' => 3,
      ],
    ];

    // The expected count is the count returned by the query before the query
    // is modified by SqlBase::initializeIterator().
    $tests[0]['expected_count'] = 3;
    $tests[0]['configuration'] = [
      'high_water_property' => [
        'name' => 'changed',
      ],
    ];
    $tests[0]['high_water'] = $tests[0]['source_data']['high_water_node'][0]['changed'];

    // Test high water initialized to NULL.
    $tests[1]['source_data'] = $tests[0]['source_data'];
    $tests[1]['expected_data'] = [
      [
        'id' => 1,
        'title' => 'Item 1',
        'changed' => 1,
      ],
      [
        'id' => 2,
        'title' => 'Item 2',
        'changed' => 2,
      ],
      [
        'id' => 3,
        'title' => 'Item 3',
        'changed' => 3,
      ],
    ];
    $tests[1]['expected_count'] = $tests[0]['expected_count'];
    $tests[1]['configuration'] = $tests[0]['configuration'];
    $tests[1]['high_water'] = NULL;

    // Test high water initialized to an empty string.
    $tests[2]['source_data'] = $tests[0]['source_data'];
    $tests[2]['expected_data'] = [
      [
        'id' => 1,
        'title' => 'Item 1',
        'changed' => 1,
      ],
      [
        'id' => 2,
        'title' => 'Item 2',
        'changed' => 2,
      ],
      [
        'id' => 3,
        'title' => 'Item 3',
        'changed' => 3,
      ],
    ];
    $tests[2]['expected_count'] = $tests[0]['expected_count'];
    $tests[2]['configuration'] = $tests[0]['configuration'];
    $tests[2]['high_water'] = '';

    return $tests;
  }

}
