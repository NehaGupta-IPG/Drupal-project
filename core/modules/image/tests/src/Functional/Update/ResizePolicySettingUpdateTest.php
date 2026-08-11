<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Functional\Update;

use Drupal\Core\Image\ImageResizePolicy;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests addition of new image resize_policy setting.
 */
#[Group('image')]
#[RunTestsInSeparateProcesses]
#[IgnoreDeprecations]
class ResizePolicySettingUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.filled.standard.php.gz',
    ];
  }

  /**
   * Tests image_post_update_add_resize_policy().
   */
  public function testImagePostUpdateAddResizePolicy(): void {
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

    // Confirm that resize policy is added to config and set to resize larger
    // images by default.
    $storage = \Drupal::entityTypeManager()->getStorage('field_config');
    $imageFields = $storage->loadMultiple();
    if (!empty($imageFields)) {
      foreach ($imageFields as $field) {
        if ($field->getType() === 'image') {
          $this->assertEquals(ImageResizePolicy::ResizeLargerImages, $field->getSetting('resize_policy'));
        }
      }
    }
  }

}
