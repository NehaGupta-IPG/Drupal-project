<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Functional\Update;

use Drupal\Component\Uuid\Uuid;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\Tests\Traits\Core\CronRunTrait;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that block content revision UUID field update.
 */
#[CoversFunction('block_content_update_11001')]
#[Group('block_content')]
#[RunTestsInSeparateProcesses]
class BlockContentUuidUpdateTest extends UpdatePathTestBase {

  use CronRunTrait;

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
    ];
  }

  /**
   * Tests block_content_update_11001().
   */
  public function testRevisionUuidUpdate() {
    $this->runUpdates();
    $this->cronRun();

    $block_contents = \Drupal::entityTypeManager()->getStorage('block_content')->loadMultiple();
    foreach ($block_contents as $block_content) {
      $this->assertTrue(Uuid::isValid($block_content->get('revision_uuid')->value));
    }
  }

}
