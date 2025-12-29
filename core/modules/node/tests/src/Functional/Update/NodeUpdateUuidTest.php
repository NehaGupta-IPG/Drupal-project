<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Functional\Update;

use Drupal\Component\Uuid\Uuid;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\Tests\Traits\Core\CronRunTrait;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that node revision UUID field update.
 */
#[CoversFunction('node_update_11001')]
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class NodeUpdateUuidTest extends UpdatePathTestBase {

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
   * Tests node_update_11001().
   */
  public function testRevisionUuidUpdate() {
    $this->runUpdates();
    $this->cronRun();

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple();
    foreach ($nodes as $node) {
      $this->assertTrue(Uuid::isValid($node->get('revision_uuid')->value));
    }
  }

}
