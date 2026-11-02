<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\image\Entity\ImageStyle;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\media\MediaMetadataRefresher;
use Drupal\media\MediaSourceStatus;
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
   * {@inheritdoc}
   */
  protected static $modules = ['language', 'content_translation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => 'field_to_map_to',
      'type' => 'string',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $this->testMediaType->id(),
      'label' => 'Field to map to',
      'translatable' => TRUE,
    ])->save();
    $this->testMediaType->setFieldMap([
      'attribute_to_map' => 'field_to_map_to',
    ])->save();
  }

  /**
   * Sets the metadata the test media source reports.
   */
  protected function setSourceMetadata(?string $value, string $thumbnail_uri): void {
    file_put_contents($thumbnail_uri, '');
    $this->container->get('state')->set('media_source_test_attributes', [
      'attribute_to_map' => ['value' => $value],
      'thumbnail_uri' => ['value' => $thumbnail_uri],
    ]);
  }

  /**
   * Creates a media item of the test type.
   */
  protected function createTestMedia(): MediaInterface {
    $media = Media::create([
      'bundle' => $this->testMediaType->id(),
      'name' => 'Test media',
      'field_media_test' => 'source',
    ]);
    $media->save();
    return $media;
  }

  /**
   * Tests that an enforced update overwrites mapped fields once.
   */
  public function testEnforcedUpdateOverwritesMappedFields(): void {
    $this->setSourceMetadata('Old value', 'public://old.png');
    $media = $this->createTestMedia();
    $this->assertSame('Old value', $media->get('field_to_map_to')->value);

    $this->setSourceMetadata('New value', 'public://new.png');
    // A normal save leaves stored values alone.
    $media->save();
    $this->assertSame('Old value', $media->get('field_to_map_to')->value);
    $this->assertSame('public://old.png', $media->thumbnail->entity->getFileUri());

    $this->assertFalse($media->isMetadataUpdateEnforced());
    $media->enforceMetadataUpdate()->save();
    $this->assertSame('New value', $media->get('field_to_map_to')->value);
    $this->assertSame('public://new.png', $media->thumbnail->entity->getFileUri());
    // The flag only applies to the save it was set for.
    $this->assertFalse($media->isMetadataUpdateEnforced());

    $this->setSourceMetadata('Newer value', 'public://newer.png');
    $media->save();
    $this->assertSame('New value', $media->get('field_to_map_to')->value);
  }

  /**
   * Tests that an enforced update keeps values the source no longer returns.
   */
  public function testEnforcedUpdateKeepsValuesWhenSourceReturnsNothing(): void {
    $this->setSourceMetadata('Stored value', 'public://stored.png');
    $media = $this->createTestMedia();

    $this->setSourceMetadata(NULL, 'public://stored.png');
    $media->enforceMetadataUpdate()->save();
    $this->assertSame('Stored value', $media->get('field_to_map_to')->value);
  }

  /**
   * Tests that an enforced update drops a thumbnail whose file is missing.
   */
  public function testEnforcedUpdateWithMissingThumbnailFile(): void {
    $this->setSourceMetadata('Value', 'public://gone.png');
    $media = $this->createTestMedia();
    unlink('public://gone.png');

    $media->enforceMetadataUpdate()->save();
    $default_uri = $this->config('media.settings')->get('icon_base_uri') . '/generic.png';
    $this->assertSame($default_uri, $media->thumbnail->entity->getFileUri());
  }

  /**
   * Tests that an enforced update replaces queued thumbnails immediately.
   */
  public function testEnforcedUpdateWithQueuedThumbnails(): void {
    $this->testMediaType->setQueueThumbnailDownloadsStatus(TRUE)->save();
    $this->setSourceMetadata('Value', 'public://queued.png');
    $media = $this->createTestMedia();
    $queue = $this->container->get('queue')->get('media_entity_thumbnail');
    $queue->deleteQueue();

    $this->setSourceMetadata('Value', 'public://refreshed.png');
    $media->enforceMetadataUpdate()->save();
    $this->assertSame('public://refreshed.png', $media->thumbnail->entity->getFileUri());
    $this->assertSame(0, $queue->numberOfItems());
  }

  /**
   * Tests that an enforced update refreshes every translation.
   */
  public function testEnforcedUpdateRefreshesTranslations(): void {
    $this->installConfig(['language']);
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->container->get('content_translation.manager')->setEnabled('media', $this->testMediaType->id(), TRUE);

    $this->setSourceMetadata('Old value', 'public://old.png');
    $media = $this->createTestMedia();
    $media->addTranslation('fr', ['name' => 'Test media (French)'])->save();

    $this->setSourceMetadata('New value', 'public://new.png');
    $media->enforceMetadataUpdate()->save();

    $media = Media::load($media->id());
    foreach (['en', 'fr'] as $langcode) {
      $translation = $media->getTranslation($langcode);
      $this->assertSame('New value', $translation->get('field_to_map_to')->value, $langcode);
      $this->assertSame('public://new.png', $translation->thumbnail->entity->getFileUri(), $langcode);
    }

    // Each translation sees the enforced update, so none keeps pointing at a
    // thumbnail file that has gone missing.
    unlink('public://new.png');
    $media->enforceMetadataUpdate()->save();
    $default_uri = $this->config('media.settings')->get('icon_base_uri') . '/generic.png';
    foreach (['en', 'fr'] as $langcode) {
      $this->assertSame($default_uri, $media->getTranslation($langcode)->thumbnail->entity->getFileUri(), $langcode);
    }
  }

  /**
   * Tests the source checks made before refreshing.
   */
  public function testSourceStatus(): void {
    $refresher = $this->container->get(MediaMetadataRefresher::class);

    $media = $this->createTestMedia();
    $this->assertSame(MediaSourceStatus::Available, $refresher->check($media));
    $empty = Media::create(['bundle' => $this->testMediaType->id()]);
    $this->assertSame(MediaSourceStatus::Missing, $refresher->check($empty));

    $file_media = $this->generateMedia('present.txt', $this->createMediaType('file'));
    $file_media->save();
    $file = $file_media->get('field_media_file')->entity;
    $this->assertSame(MediaSourceStatus::Available, $refresher->check($file_media));

    // The file entity remains, but its file is gone.
    unlink($file->getFileUri());
    $this->assertSame(MediaSourceStatus::Missing, $refresher->check(Media::load($file_media->id())));

    // The file entity itself is gone.
    $file->delete();
    $this->container->get('entity_type.manager')->getStorage('media')->resetCache();
    $this->assertSame(MediaSourceStatus::Missing, $refresher->check(Media::load($file_media->id())));
  }

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
