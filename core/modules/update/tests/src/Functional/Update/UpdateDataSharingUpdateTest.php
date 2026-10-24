<?php

declare(strict_types=1);

namespace Drupal\Tests\update\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\update\DataSharing;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests adding the data sharing settings to existing sites.
 *
 * @see update_post_update_add_data_sharing_settings()
 */
#[Group('Update')]
#[Group('update')]
#[CoversFunction('update_post_update_add_data_sharing_settings')]
#[RunTestsInSeparateProcesses]
class UpdateDataSharingUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests that existing sites start undecided with a notice period.
   */
  public function testDataSharingSettings(): void {
    $this->runUpdates();
    $this->assertSame(['domain' => NULL, 'site_mail' => NULL], $this->config('update.settings')->get('data_sharing'));
    $this->assertIsInt($this->container->get('state')->get(DataSharing::NOTICE_START_STATE_KEY));
  }

}
