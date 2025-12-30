<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Functional\Update;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\Tests\Traits\Core\CronRunTrait;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that revision_uuid base field install.
 */
#[CoversFunction('system_update_11301')]
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class InstallRevisionUuidFieldUpdateTest extends UpdatePathTestBase {

  use CronRunTrait;

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
    ];
  }

  /**
   * Tests system_update_11301().
   */
  public function testRevisionUuidUpdate(): void {
    $this->runUpdates();
    $this->runQueues();

    $manager = \Drupal::entityTypeManager();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    foreach ($manager->getDefinitions() as $entityTypeId => $entityType) {
      if (!$entityType instanceof ContentEntityTypeInterface || !$entityType->hasRevisionMetadataKey('revision_uuid')) {
        continue;
      }

      $storage = $manager->getStorage($entityTypeId);
      $fieldName = $entityType->getRevisionMetadataKey('revision_uuid');
      foreach ($storage->loadMultiple() as $entity) {
        $this->assertTrue(Uuid::isValid($entity->get($fieldName)->value));
      }
    }
  }

  /**
   * Runs all revision_uuid_update:* queues until it consumes them.
   *
   * For practical reasons we don't test by running cron because it's expensive,
   * and might need several runs. Also, we're only interested in running the
   * revision_uuid_update:* queues.
   */
  protected function runQueues(): void {
    $workerManager = \Drupal::service('plugin.manager.queue_worker');
    foreach ($workerManager->getDefinitions() as $pluginId => $definition) {
      $worker = $workerManager->createInstance($pluginId);

      if ($worker->getBaseId() === 'revision_uuid_update') {
        $queue = \Drupal::queue($worker->getPluginId());
        $queue->createQueue();

        while ($queue->numberOfItems()) {
          $item = $queue->claimItem();
          $worker->processItem($item->data);
          $queue->deleteItem($item);
        }
      }
    }
  }

}
