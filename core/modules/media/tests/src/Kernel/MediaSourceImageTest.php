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

}
