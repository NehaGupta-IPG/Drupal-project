<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Kernel;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the image media source.
 */
#[Group('media')]
#[RunTestsInSeparateProcesses]
class MediaSourceImageTest extends MediaKernelTestBase {

  use TestFileCreationTrait;

  /**
   * Creates a saved file entity from TestFileCreationTrait images.
   *
   * @param int $index
   *   The index of the test image to use.
   *
   * @return \Drupal\file\Entity\File
   *   The saved file entity.
   */
  protected function createImageFile(int $index = 0): File {
    $file = File::create([
      'uri' => $this->getTestFiles('image')[$index]->uri,
    ]);
    $file->save();
    return $file;
  }

  /**
   * Test thumbnail alt text is updated when source  field alt text is changed.
   */
  public function testAltTextSourceFieldChangeTriggersThumbnailUpdate(): void {
    $mediaType = $this->createMediaType('image');

    $file = File::create([
      'uri' => $this->getTestFiles('image')[0]->uri,
    ]);
    $file->save();

    $originalAlt = $this->randomString();
    $media = Media::create([
      'name' => $this->randomString(),
      'bundle' => $mediaType->id(),
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $originalAlt,
      ],
    ]);
    $media->save();

    $this->assertEquals($originalAlt, $media->get('thumbnail')->alt);

    $updatedAlt = $this->randomString();
    $media->get('field_media_image')->alt = $updatedAlt;
    $media->save();

    $this->assertEquals($updatedAlt, $media->get('thumbnail')->alt);
  }

  /**
   * Tests new media item replaces thumbnail set before saving.
   */
  public function testNewMediaWithPresetThumbnailIsUpdated(): void {
    $media_type = $this->createMediaType('image');
    $source_file = $this->createImageFile(0);
    $preset_file = $this->createImageFile(1);

    $source_alt = $this->randomMachineName();
    $media = Media::create([
      'name' => $this->randomMachineName(),
      'bundle' => $media_type->id(),
      'field_media_image' => [
        'target_id' => $source_file->id(),
        'alt' => $source_alt,
      ],
      'thumbnail' => [
        'target_id' => $preset_file->id(),
        'alt' => 'Preset alt',
      ],
    ]);
    $media->save();

    // Media item is new, preset thumbnail alt should be replaced.
    $this->assertEquals($source_file->id(), $media->get('thumbnail')->target_id);
    $this->assertSame($source_alt, $media->get('thumbnail')->alt);
  }

}
