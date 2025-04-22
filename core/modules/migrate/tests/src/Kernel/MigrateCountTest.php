<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate\Kernel;

/**
 * Tests row and message counts.
 *
 * @group migrate
 */
class MigrateCountTest extends MigrateTestBase {

  /**
   * Migration definition.
   */
  public array $definition;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate',
    'migrate_count_test',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->definition = [
      'id' => 'vocabularies',
      'source' => [
        'plugin' => 'count_test',
        'data_rows' => [
          ['id' => '1', 'name' => 'categories', 'data' => [1, 2]],
        ],
        'ids' => ['id' => ['type' => 'integer']],
      ],
      'process' => [
        'vid' => 'id',
        'name' => 'name',
        'weight' => 'weight',
        'fail' => [
          'plugin' => 'flatten',
          'source' => 'data',
        ],
      ],
      'destination' => ['plugin' => 'entity:taxonomy_vocabulary'],
    ];
  }

  /**
   * Tests map message deletion.
   *
   * @param array $test_data_row
   *   A test data row array to be added to migration source data rows.
   * @param array $expected_results
   *   An associative array of expected migration results .
   *   - source_plugin_count: How many source records were used by migration.
   *   - imported_count: How many rows were imported on migration.
   *   - processed_count: How many rows were processed on migration.
   *   - all_rows_processed: TRUE if this migration is complete otherwise FALSE..
   *
   * @dataProvider providerTestNext
   */
  public function testNext($test_data_row, $expected_results): void {
    $this->definition['source']['data_rows'][] = $test_data_row;

    // Create the migration an execute.
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = \Drupal::service('plugin.manager.migration')
      ->createStubMigration($this->definition);
    $this->executeMigration($migration);

    $id_map = $migration->getIdMap();

    $this->assertCount($expected_results['source_plugin_count'], $migration->getSourcePlugin());
    $this->assertSame($expected_results['imported_count'], $id_map->importedCount());
    $this->assertSame($expected_results['processed_count'], $id_map->processedCount());
    $this->assertSame($expected_results['all_rows_processed'], $migration->allRowsProcessed());
  }

  /**
   * Data provider for testNext().
   */
  public static function providerTestNext(): array {
    return [
      'both rows successful' => [
        'test_data_row' => [
          'id' => '2',
          'name' => 'tags',
          'data' => [3, 4],
        ],
        'expected_results' => [
          'source_plugin_count' => 2,
          'imported_count' => 2,
          'processed_count' => 2,
          'all_rows_processed' => TRUE,
        ],
      ],
      'throw skip in flatten' => [
        'test_data_row' => [
          'id' => '2',
          'name' => 'tags',
          'data' => 'string',
        ],
        'expected_results' => [
          'source_plugin_count' => 2,
          'imported_count' => 1,
          'processed_count' => 2,
          'all_rows_processed' => TRUE,
        ],
      ],
      'prepare row false' => [
        'test_data_row' => [
          'id' => '2',
          'name' => 'tags',
          'data' => 'false',
        ],
        'expected_results' => [
          'source_plugin_count' => 2,
          'imported_count' => 1,
          'processed_count' => 2,
          'all_rows_processed' => TRUE,
        ],
      ],
    ];
  }

}
