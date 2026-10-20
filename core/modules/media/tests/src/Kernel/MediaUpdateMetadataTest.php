<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Drupal\media\Plugin\media\Source\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the update metadata method on Media entities.
 */
#[Group('media')]
#[RunTestsInSeparateProcesses]
class MediaUpdateMetadataTest extends MediaKernelTestBase {

  /**
   * Tests that missing thumbnail metadata preserves the default thumbnail.
   */
  #[DataProvider('missingThumbnailProvider')]
  public function testMissingThumbnail(?string $thumbnail_uri): void {
    $state = $this->container->get('state');
    $state->set('media_source_test_attributes', [
      'thumbnail_uri' => ['value' => $thumbnail_uri],
    ]);

    // Flushing an empty path must not remove unrelated image derivatives.
    $style = ImageStyle::create(['name' => 'test_thumbnail', 'label' => 'Test']);
    $style->save();
    $unrelated_derivative = $style->buildUri('public://unrelated.png');
    $directory = dirname($unrelated_derivative);
    $this->container->get('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    file_put_contents($unrelated_derivative, 'unrelated image derivative');

    $media = Media::create([
      'bundle' => $this->testMediaType->id(),
      'name' => 'Media without a thumbnail',
      'field_media_test' => 'source',
    ]);
    $media->save();
    $default_uri = $this->config('media.settings')->get('icon_base_uri') . '/generic.png';
    $this->assertSame($default_uri, $media->thumbnail->entity->getFileUri());
    $this->assertFileExists($unrelated_derivative);

    $media->enforceMetadataUpdate()->save();
    $this->assertSame($default_uri, $media->thumbnail->entity->getFileUri());
    $this->assertFileExists($unrelated_derivative);

    // A later available thumbnail must still replace the default and have its
    // existing derivative flushed by the metadata update.
    $thumbnail_uri = 'public://available.png';
    file_put_contents($thumbnail_uri, '');
    $derivative = $style->buildUri($thumbnail_uri);
    file_put_contents($derivative, 'outdated image derivative');
    $state->set('media_source_test_attributes', [
      'thumbnail_uri' => ['value' => $thumbnail_uri],
    ]);
    $media->enforceMetadataUpdate()->save();
    $this->assertSame($thumbnail_uri, $media->thumbnail->entity->getFileUri());
    $this->assertFileDoesNotExist($derivative);
    $this->assertFileExists($unrelated_derivative);
  }

  /**
   * Provides missing thumbnail metadata values.
   */
  public static function missingThumbnailProvider(): array {
    return [
      'null' => [NULL],
      'empty string' => [''],
    ];
  }

  /**
   * Tests the update metadata operation.
   */
  public function testUpdateMetadata(): void {
    $media_type = $this->createMediaType('file');
    $source_plugin = $media_type->getSource();

    // Initially the "name" metadata is the filename.
    $media = $this->generateMedia('foo.txt', $media_type);
    $media->save();
    $name_metadata = $source_plugin->getMetadata($media, File::METADATA_ATTRIBUTE_NAME);
    $this->assertSame('foo.txt', $name_metadata);

    // Rename the file, simulating a remote metadata change.
    $file_id = $source_plugin->getSourceFieldValue($media);
    /** @var \Drupal\file\FileInterface $file */
    $file = $this->container->get('entity_type.manager')
      ->getStorage('file')
      ->load($file_id);
    $file->setFilename('bar.txt');
    $file->save();

    // Update metadata, we should now pick the new name.
    $media->enforceMetadataUpdate()->save();

    $name_metadata = $source_plugin->getMetadata($media, File::METADATA_ATTRIBUTE_NAME);
    $this->assertSame('bar.txt', $name_metadata);
  }

}
