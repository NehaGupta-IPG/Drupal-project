<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate\Kernel;

use Drupal\migrate\MigrateExecutable;

/**
 * Tests when migrate messages are deleted.
 *
 * @group migrate
 */
class MigrateNextTest extends MigrateTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate',
    'taxonomy',
  ];

  /**
   * Tests message deletion.
   */
  public function testNext(): void {
    // Create a migration that will generate an exception. Here the second
    // row will cause the flatten process plugin to throw a MigrateException.
    $ids = ['id' => ['type' => 'integer']];
    $definition = [
      'id' => 'vocabularies',
      'source' => [
        'plugin' => 'embedded_data',
        'data_rows' => [
          ['id' => '1', 'name' => 'categories', 'data' => [1, 2]],
          ['id' => '2', 'name' => 'tags', 'data' => 'string'],
        ],
        'ids' => $ids,
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

    // Create the migration and execute it.
    $migration = \Drupal::service('plugin.manager.migration')
      ->createStubMigration($definition);
    // Import and validate vocabulary config entities were created.
    $executable = new MigrateExecutable($migration, $this);
    $executable->import();

    /** @var \Drupal\migrate\Plugin\MigrateIdMapInterface $id_map */
    $id_map = $migration->getIdMap();

    // The two rows will be processed, one imported and one migrate message.
    $this->assertSame(1, $id_map->importedCount());
    $this->assertSame(2, $id_map->processedCount());
    $this->assertSame(1, $id_map->messageCount());

    // Without changing the source rerun the import.
    // Confirm the state of the counts and message table are the same.
    $executable->import();
    $this->assertSame(1, $id_map->importedCount());
    $this->assertSame(2, $id_map->processedCount());
    $this->assertSame(1, $id_map->messageCount());
  }

}
