<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Functional\Update;

use Drupal\Core\Image\ImageResizePolicy;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests addition of new image resize_policy setting.
 */
#[Group('image')]
#[RunTestsInSeparateProcesses]
class ResizePolicySettingUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
    ];
  }

  /**
   * Tests image_post_update_add_resize_policy().
   */
  public function testSystemPostUpdateLinksetSettings(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('field_config');
    $imageFields = $storage->loadMultiple();
    $imagesProcessed = FALSE;
    if (!empty($imageFields)) {
      foreach ($imageFields as $field) {
        if ($field->getType() === 'image') {
          $this->assertNull($field->getSetting('resize_policy'));
          $imagesProcessed = TRUE;
        }
      }
    }
    if (!$imagesProcessed) {
      $this->fail('No image fields found.');
    }

    $this->runUpdates();

    // Confirm that config settings was added and is 'resize_larger_images' by default.
    $storage = \Drupal::entityTypeManager()->getStorage('field_config');
    $imageFields = $storage->loadMultiple();
    if (!empty($imageFields)) {
      foreach ($imageFields as $field) {
        if ($field->getType() === 'image') {
          $this->assertEquals(ImageResizePolicy::ResizeLargerImages->value, $field->getSetting('resize_policy'));
        }
      }
    }
  }

}
