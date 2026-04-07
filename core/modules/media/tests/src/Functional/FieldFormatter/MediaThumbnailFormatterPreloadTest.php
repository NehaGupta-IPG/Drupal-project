<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Functional\FieldFormatter;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\Tests\media\Functional\MediaFunctionalTestBase;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that MediaThumbnailFormatter pre-loads thumbnail entities.
 *
 * Validates the fix for lazy-loading failures in translation context
 * when media thumbnail formatter renders images.
 *
 * @see \Drupal\media\Plugin\Field\FieldFormatter\MediaThumbnailFormatter::viewElements()
 * @see \Drupal\image\Hook\ImageThemeHooks::preprocessImageFormatter()
 */
#[Group('media')]
#[RunTestsInSeparateProcesses]
class MediaThumbnailFormatterPreloadTest extends MediaFunctionalTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->adminUser);

    // Create an image media type.
    $this->createMediaType('image', ['id' => 'image']);

    // Create an article content type with a media reference field.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_media_reference',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => 1,
      'settings' => ['target_type' => 'media'],
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
      'label' => 'Reference media',
    ])->save();

    $this->container->get('entity_display.repository')
      ->getFormDisplay('node', 'article')
      ->setComponent('field_media_reference', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->save();
  }

  /**
   * Tests that thumbnail entities are pre-loaded before rendering.
   *
   * Verifies the fix: MediaThumbnailFormatter now pre-loads the thumbnail
   * file entity before passing it to the image formatter theme hook, preventing
   * undefined property warnings from lazy-loading in translation context.
   */
  public function testThumbnailEntityPreload(): void {
    // Create and upload an image file.
    $file = File::create([
      'uri' => current($this->getTestFiles('image'))->uri,
    ]);
    $file->save();

    // Create a media item with the image.
    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Test Image',
      'field_media_image' => $file->id(),
    ]);
    $media->save();

    // Setup display with media_thumbnail formatter.
    $display = $this->container->get('entity_display.repository')
      ->getViewDisplay('node', 'article', 'default');
    $display->setComponent('field_media_reference', [
      'type' => 'media_thumbnail',
      'settings' => [
        'image_link' => '',
        'image_style' => '',
        'image_loading' => ['attribute' => 'lazy'],
      ],
    ]);
    $display->save();

    // Create a node referencing the media.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test Article',
      'field_media_reference' => ['target_id' => $media->id()],
    ]);

    // Build the render array - exercises the pre-loading fix.
    $build = $display->build($node);

    // Verify field rendered successfully.
    $this->assertNotEmpty($build['field_media_reference'], 'Field rendered without errors');

    // Get the first rendered item.
    $numeric_keys = array_filter(array_keys($build['field_media_reference']), 'is_int');
    $this->assertNotEmpty($numeric_keys, 'Field has rendered items');

    $first_key = reset($numeric_keys);
    $item_build = $build['field_media_reference'][$first_key];

    // Verify image formatter was used.
    $this->assertEquals('image_formatter', $item_build['#theme']);

    // Critical test: verify thumbnail item was pre-loaded.
    $item = $item_build['#item'];
    $this->assertTrue(isset($item->_loaded), 'Item has _loaded flag');
    $this->assertTrue($item->_loaded, 'Thumbnail entity is pre-loaded');
    $this->assertNotNull($item->entity, 'Entity is not null');
  }

  /**
   * Tests that image styles work with pre-loaded thumbnails.
   *
   * This test validates that the fix works specifically with image styles,
   * which is where the original bug manifested ("Could not apply Portrait SM").
   */
  public function testThumbnailWithImageStyle(): void {
    // Create and upload an image file.
    $file = File::create([
      'uri' => current($this->getTestFiles('image'))->uri,
    ]);
    $file->save();

    // Create a media item.
    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Test Image',
      'field_media_image' => $file->id(),
    ]);
    $media->save();

    // Setup display with media_thumbnail formatter and image style.
    $display = $this->container->get('entity_display.repository')
      ->getViewDisplay('node', 'article', 'default');
    $display->setComponent('field_media_reference', [
      'type' => 'media_thumbnail',
      'settings' => [
        'image_link' => '',
        'image_style' => 'thumbnail',
        'image_loading' => ['attribute' => 'lazy'],
      ],
    ]);
    $display->save();

    // Create a node.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test Article',
      'field_media_reference' => ['target_id' => $media->id()],
    ]);

    // Build and verify image style processing works.
    $build = $display->build($node);
    $this->assertNotEmpty($build['field_media_reference']);

    $numeric_keys = array_filter(array_keys($build['field_media_reference']), 'is_int');
    $first_key = reset($numeric_keys);
    $item_build = $build['field_media_reference'][$first_key];

    // The image style should be set in the render array.
    $this->assertEquals('thumbnail', $item_build['#image_style']);

    // Verify the item has the necessary properties for image style processing.
    $item = $item_build['#item'];
    $this->assertTrue($item->_loaded, 'Item is pre-loaded');
    $this->assertNotNull($item->entity, 'Entity is available for URI access');
  }

}
