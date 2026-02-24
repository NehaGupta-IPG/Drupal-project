<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Testing the media settings update.
 */
#[Group('media')]
#[RunTestsInSeparateProcesses]
class MediaSettingsUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/11.0-x-minimal-media.php.gz',
    ];
  }

  /**
   * Tests update path for media oembed discovery setting.
   */
  public function testRunUpdates(): void {
    self::assertNull(\Drupal::config('media.settings')->get('oembed_discovery'));
    $this->runUpdates();
    self::assertTrue(\Drupal::config('media.settings')->get('oembed_discovery'));
  }

}
