<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Functional\update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the update path to add deletion settings to Media.
 */
#[Group('media')]
#[CoversFunction('media_post_update_create_new_settings')]
class DeleteFormSettingsTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/standard-10.3-media-installed.php',
    ];
  }

  /**
   * Tests that the update path adds new settings.
   */
  public function testSettingsAreCreated(): void {
    $this->assertNull($this->config('media.settings')->get('delete_files'));
    $this->runUpdates();
    $this->assertTrue($this->config('media.settings')->get('delete_files'));
  }

}
