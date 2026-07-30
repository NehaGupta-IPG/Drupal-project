<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the post_update hook to add display checkbox on image fields.
 *
 * @see image_post_update_add_display_checkbox()
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class AddDisplayCheckboxUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['image', 'node', 'field'];

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.filled.standard.php.gz',
    ];
  }

  /**
   * Tests that display field is added to the image fields.
   */
  public function testAddDisplayCheckboxUpdate(): void {
    $schema = \Drupal::database()->schema();

    // Check that schema doesn't have checkbox field.
    $this->assertFalse($schema->fieldExists('node__field_image', 'field_image_display'));
    $this->assertFalse($schema->fieldExists('node_revision__field_image', 'field_image_display'));

    $this->runUpdates();

    // Check that schema has checkbox field.
    $this->assertTrue($schema->fieldExists('node__field_image', 'field_image_display'));
    $this->assertTrue($schema->fieldExists('node_revision__field_image', 'field_image_display'));
  }

}
