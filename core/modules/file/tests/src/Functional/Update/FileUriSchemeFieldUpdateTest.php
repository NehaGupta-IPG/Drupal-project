<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests adding the 'uri_scheme_field' setting to file field storages.
 *
 * @see file_post_update_add_uri_scheme_field()
 */
#[Group('Update')]
#[Group('file')]
#[CoversFunction('file_post_update_add_uri_scheme_field')]
#[RunTestsInSeparateProcesses]
class FileUriSchemeFieldUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests that the setting is added to existing image field storages.
   */
  public function testUpdate(): void {
    $settings = $this->config('field.storage.user.user_picture')->get('settings');
    $this->assertArrayNotHasKey('uri_scheme_field', $settings);

    $this->runUpdates();

    $this->assertFalse($this->config('field.storage.user.user_picture')->get('settings.uri_scheme_field'));
  }

}
