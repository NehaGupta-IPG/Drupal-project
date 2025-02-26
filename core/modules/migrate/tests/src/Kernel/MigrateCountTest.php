<?php

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
   * @dataProvider providerTestNext
   */
  public function testNext($row_1, $expected): void {
    $this->definition['source']['data_rows'][1] = $row_1;

    // Create the migration an execute.
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = \Drupal::service('plugin.manager.migration')
      ->createStubMigration($this->definition);
    $this->executeMigration($migration);

    $id_map = $migration->getIdMap();

    $this->assertCount($expected['source_plugin_count'], $migration->getSourcePlugin());
    $this->assertSame($expected['imported_count'], $id_map->importedCount());
    $this->assertSame($expected['processed_count'], $id_map->processedCount());
    $this->assertSame($expected['all_rows_processed'], $migration->allRowsProcessed());
  }

  /**
   * Data provider for testNext().
   */
  public static function providerTestNext(): array {
    return [
      'both rows successful' => [
        'row_1' => [
          'id' => '2',
          'name' => 'tags',
          'data' => [3, 4],
        ],
        'expected' => [
          'source_plugin_count' => 2,
          'imported_count' => 2,
          'processed_count' => 2,
          'all_rows_processed' => TRUE,
        ],
      ],
      'throw skip in flatten' => [
        'row_1' => [
          'id' => '2',
          'name' => 'tags',
          'data' => 'string',
        ],
        'expected' => [
          'source_plugin_count' => 2,
          'imported_count' => 1,
          'processed_count' => 2,
          'all_rows_processed' => TRUE,
        ],
      ],
      'prepare row false' => [
        'row_1' => [
          'id' => '2',
          'name' => 'tags',
          'data' => 'false',
        ],
        'expected' => [
          'source_plugin_count' => 2,
          'imported_count' => 1,
          'processed_count' => 2,
          'all_rows_processed' => TRUE,
        ],
      ],
    ];
  }

}
