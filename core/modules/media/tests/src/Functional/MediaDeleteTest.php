<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Functional;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Form\MediaDeleteForm;
use Drupal\media\Form\MediaDeleteMultipleForm;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests deleting media items.
 */
#[Group('media')]
#[CoversClass(MediaDeleteForm::class)]
#[CoversClass(MediaDeleteMultipleForm::class)]
class MediaDeleteTest extends BrowserTestBase {

  use TestFileCreationTrait;
  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests deleting the file associated with a media item.
   */
  public function testDeleteFileAssociatedWithMedia(): void {
    $media_type = $this->createMediaType('image')->id();
    [$image1, $image2] = $this->getTestFiles('image');
    $this->assertIsObject($image1);
    $this->assertObjectHasProperty('uri', $image1);
    $this->assertIsObject($image2);
    $this->assertObjectHasProperty('uri', $image2);

    $editor1 = $this->createUser([
      "delete any $media_type media",
      'delete own files',
    ]);
    $editor2 = $this->createUser([
      "delete any $media_type media",
    ]);
    $editor3 = $this->createUser([
      "delete any $media_type media",
      'delete any file',
    ]);

    $file1 = File::create([
      'uri' => $image1->uri,
      'uid' => $editor1,
    ]);
    $file1->save();
    $media1 = Media::create([
      'bundle' => $image_type->id(),
      'name' => $this->randomMachineName(),
      'field_media_image' => $file1,
    ]);
    $media1->save();
    $file2 = File::create([
      'uri' => $image2->uri,
      'uid' => $editor2->id(),
    ]);
    $file2->save();
    $media2 = Media::create([
      'bundle' => $image_type->id(),
      'name' => $this->randomMachineName(),
      'field_media_image' => $file2,
    ]);
    $media2->save();
    $media3 = Media::create([
      'bundle' => $image_type->id(),
      'name' => $this->randomMachineName(),
      'field_media_image' => $file1,
    ]);
    $media3->save();

    $this->drupalLogin($editor1);
    $this->drupalGet($media1->toUrl('delete-form'));
    $assert_session = $this->assertSession();
    // This file is used by two media items so there is no option to delete it.
    $assert_session->fieldNotExists('also_delete_file');
    $assert_session->pageTextContains('The file attached to this media is used in 1 other place and will be retained.');

    $media3->delete();
    // Now there's only one usage.
    $this->getSession()->reload();
    $assert_session->fieldExists('also_delete_file');
    $assert_session->pageTextNotContains('The file attached to this media is used in 1 other place and will be retained.');

    $this->submitForm(['also_delete_file' => 1], 'Delete');
    $entity_type_manager = \Drupal::entityTypeManager();
    $this->assertNull($entity_type_manager->getStorage('media')->loadUnchanged($media1->id()));
    $this->assertNull($entity_type_manager->getStorage('file')->loadUnchanged($file1->id()));

    $this->drupalGet($media2->toUrl('delete-form'));
    // The user does no have permission to delete the file.
    $assert_session->fieldNotExists('also_delete_file');
    $assert_session->pageTextContains('The file attached to this media is owned by ' . $editor2->getDisplayName() . ' so will be retained.');

    // Users with the `delete any files` permission can delete the file.
    $this->drupalLogin($editor3);
    $this->drupalGet($media2->toUrl('delete-form'));
    $assert_session->fieldExists('also_delete_file');
    $assert_session->submitForm(['also_delete_file' => 1], 'Delete');
    $this->assertNull($entity_type_manager->getStorage('media')->loadUnchanged($media2->id()));
    $this->assertNull($entity_type_manager->getStorage('file')->loadUnchanged($file2->id()));
  }

}
