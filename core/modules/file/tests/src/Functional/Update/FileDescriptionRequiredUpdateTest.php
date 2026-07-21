<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests adding the file field 'required description' setting.
 */
#[Group('file')]
#[Group('legacy')]
#[RunTestsInSeparateProcesses]
class FileDescriptionRequiredUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      // Using the 'filled' fixture as 'bare' contains no file fields.
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.filled.standard.php.gz',
    ];
  }

  /**
   * Tests the post update hook file_post_update_description_required.
   */
  public function testFilePostUpdateDescriptionRequired(): void {
    // Check that before running updates the file field setting is not present.
    $field_config = $this->config('field.field.node.test_content_type.field_test_9');
    $this->assertArrayNotHasKey('description_field_required', $field_config->get('settings'));

    // Run updates.
    $this->runUpdates();

    // Check that after running updates the file field setting is set to  FALSE.
    $field_config = $this->config('field.field.node.test_content_type.field_test_9');
    $this->assertArrayHasKey('description_field_required', $field_config->get('settings'));
    $this->assertFalse($field_config->get('settings.description_field_required'));
  }

}
