<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that changing the image alt text updates mapped metadata.
 */
#[Group('media')]
#[RunTestsInSeparateProcesses]
class MediaImageAltTextUpdateTest extends MediaKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a media type using the image source.
    $media_type = $this->createMediaType('image', [
      'id' => 'test_image_media',
      'label' => 'Test Image Media',
    ]);

    // Create field storage for metadata copy.
    $storage = FieldStorageConfig::create([
      'field_name'    => 'field_media_image_alt_copy',
      'entity_type'   => 'media',
      'type'          => 'string',
      'cardinality'   => 1,
    ]);
    $storage->save();

    // Create field instance on the bundle.
    $field = FieldConfig::create([
      'field_name'  => 'field_media_image_alt_copy',
      'entity_type' => 'media',
      'bundle'      => $media_type->id(),
      'label'       => 'ALT Copy',
    ]);
    $field->save();

    // Map thumbnail_alt_value metadata → this field.
    $media_type->set('field_map', [
      'thumbnail_alt_value' => 'field_media_image_alt_copy',
    ]);
    $media_type->save();
  }

  /**
   * Creates a valid test image file.
   */
  protected function createTestImageFile(string $name = 'test.jpg'): File {
    $uri = "public://$name";
    file_put_contents($uri, file_get_contents('data://text/plain;base64,iVBORw0KGgo='));

    $file = File::create([
      'uri' => $uri,
      'status' => 1,
    ]);
    $file->save();
    return $file;
  }

  /**
   * The metadata should update when only the ALT changes.
   */
  public function testAltChangeUpdatesMetadata(): void {
    $file = $this->createTestImageFile();

    $media = Media::create([
      'bundle' => 'test_image_media',
      'name' => 'Test item',
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => 'Initial ALT',
      ],
    ]);
    $media->save();

    // Metadata mapped to field_media_image_alt_copy should equal Initial ALT.
    $this->assertSame(
      'Initial ALT',
      $media->get('field_media_image_alt_copy')->value,
      'Initial metadata mapped correctly.'
    );

    // Update only ALT.
    $media->field_media_image->alt = 'Updated ALT';
    $media->save();

    // Reload to verify metadata updated.
    $media = Media::load($media->id());

    $this->assertSame(
      'Updated ALT',
      $media->get('field_media_image_alt_copy')->value,
      'ALT change correctly updated metadata.'
    );
  }

  /**
   * Unrelated changes must NOT update mapped metadata.
   */
  public function testNoMetadataUpdateOnUnrelatedChange(): void {
    $file = $this->createTestImageFile('test2.jpg');

    $media = Media::create([
      'bundle' => 'test_image_media',
      'name' => 'Example',
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => 'Original ALT',
        'title' => 'Example title',
      ],
    ]);
    $media->save();

    $this->assertSame(
      'Original ALT',
      $media->get('field_media_image_alt_copy')->value,
      'Initial metadata populated correctly.'
    );

    // Change only the title → alt remains unchanged → metadata should NOT update.
    $media->field_media_image->title = 'New Title';
    $media->save();

    $media = Media::load($media->id());

    $this->assertSame(
      'Original ALT',
      $media->get('field_media_image_alt_copy')->value,
      'No metadata update occurred for unrelated change.'
    );
  }

}
