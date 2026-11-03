<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_drupal\Kernel\d7;

use Drupal\migrate_drupal\MigrationState;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Ensures MigrationState tolerates migrations missing a provider.
 */
#[Group('migrate_drupal')]
#[RunTestsInSeparateProcesses]
final class MigrationStateDestinationModuleTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'migrate_drupal',
    'options',
    'file',
    'comment',
    'node',
    'migration_provider_null_test',
  ];

  /**
   * Ensures the entity:action destination no longer triggers a TypeError.
   */
  public function testNullProviderDestinationModuleLookup(): void {
    $messenger = $this->container->get('messenger');
    $messenger->deleteAll();

    $migration = $this->container->get('plugin.manager.migration')
      ->createInstances(['d7_action'])['d7_action'];
    $this->assertNull($migration->provider);

    $migration_state = $this->container->get(MigrationState::class);
    $states = $migration_state->getUpgradeStates(7, $this->buildSourceSystemData(), [$migration]);

    $errors = array_map('strval', $messenger->messagesByType('error'));
    $this->assertContains('Destination module not found for d7_action.', $errors);
    $this->assertArrayHasKey(MigrationState::FINISHED, $states);
    $this->assertStringContainsString('system', $states[MigrationState::FINISHED]['system']);
  }

  /**
   * Builds the source system data array expected by MigrationState.
   *
   * @return array
   *   The module data keyed by module name.
   */
  private function buildSourceSystemData(): array {
    $data = ['module' => []];
    $result = $this->sourceDatabase->select('system', 's')
      ->fields('s', ['name', 'status'])
      ->condition('type', 'module')
      ->execute();
    foreach ($result as $record) {
      $data['module'][$record->name] = [
        'name' => $record->name,
        'status' => (bool) $record->status,
      ];
    }
    return $data;
  }

}
