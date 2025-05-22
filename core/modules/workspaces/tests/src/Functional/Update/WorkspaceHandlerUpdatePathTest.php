<?php

declare(strict_types=1);

namespace Drupal\Tests\workspaces\Functional\Update;

use Drupal\block_content\BlockContentWorkspaceHandler;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\workspaces\Entity\Handler\DefaultWorkspaceHandler;
use Drupal\workspaces\Entity\Handler\IgnoredWorkspaceHandler;

/**
 * Tests the update path for workspace handler in entity type definitions.
 *
 * @group workspaces
 */
class WorkspaceHandlerUpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $checkEntityFieldDefinitionUpdates = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/workspaces.php',
    ];
  }

  /**
   * Tests the update path for workspace handlers in entity type definitions.
   */
  public function testRunUpdates(): void {
    $handlers = [
      'node' => DefaultWorkspaceHandler::class,
      'block_content' => BlockContentWorkspaceHandler::class,
      'file' => IgnoredWorkspaceHandler::class,
    ];

    foreach (array_keys($handlers) as $entity_type_id) {
      /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type */
      $entity_type = \Drupal::entityDefinitionUpdateManager()->getEntityType($entity_type_id);
      $this->assertNull($entity_type->getHandlerClass('workspace'));
    }

    $this->runUpdates();

    foreach ($handlers as $entity_type_id => $handler) {
      /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type */
      $entity_type = \Drupal::entityDefinitionUpdateManager()->getEntityType($entity_type_id);
      $this->assertSame($handler, $entity_type->getHandlerClass('workspace'));
    }
  }

}
