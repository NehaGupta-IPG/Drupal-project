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
use PHPUnit\Framework\Attributes\Group;

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
   * {@inheritdoc}
   */
  protected static $modules = [
    'media',
    'media_test_views',
  ];

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
      'bundle' => $media_type,
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
      'bundle' => $media_type,
      'name' => $this->randomMachineName(),
      'field_media_image' => $file2,
    ]);
    $media2->save();
    $media3 = Media::create([
      'bundle' => $media_type,
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
    $this->submitForm(['also_delete_file' => 1], 'Delete');
    $this->assertNull($entity_type_manager->getStorage('media')->loadUnchanged($media2->id()));
    $this->assertNull($entity_type_manager->getStorage('file')->loadUnchanged($file2->id()));
  }

  /**
   * Tests deleting multiple media items at once.
   */
  public function testDeleteMultiple(): void {
    $image_type = $this->createMediaType('image');
    $image1 = $this->getTestFiles('image')[0];
    $image2 = $this->getTestFiles('image')[1];
    $image3 = $this->getTestFiles('image')[2];
    $this->assertIsObject($image1);
    $this->assertObjectHasProperty('uri', $image1);
    $this->assertIsObject($image2);
    $this->assertObjectHasProperty('uri', $image2);
    $this->assertIsObject($image3);
    $this->assertObjectHasProperty('uri', $image3);
    $editor1 = $this->createUser([
      sprintf('delete any %s media', $image_type->id()),
      'delete own files',
      'access media overview',
      'view media',
    ]);
    $editor2 = $this->createUser([
      sprintf('delete any %s media', $image_type->id()),
      'delete any file',
      'access media overview',
      'view media',
    ]);

    $file1 = File::create([
      'uri' => $image1->uri,
      'status' => 1,
      'uid' => $editor1->id(),
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
      'status' => 1,
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
    $file3 = File::create([
      'uri' => $image3->uri,
      'status' => 1,
      'uid' => $editor1->id(),
    ]);
    $file3->save();
    $media4 = Media::create([
      'bundle' => $image_type->id(),
      'name' => $this->randomMachineName(),
      'field_media_image' => $file3,
    ]);
    $media4->save();

    $this->drupalLogin($editor1);
    $this->drupalGet('test-media-bulk-form');
    $assert_session = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();

    $page->checkField('media_bulk_form[0]');
    $page->selectFieldOption('action', 'media_delete_action');
    $page->pressButton('Apply to selected items');
    $page->checkField('also_delete_file');
    $page->pressButton('Delete');
    $assert_session->pageTextContains('Deleted 1 item.');
    $entity_type_manager = \Drupal::entityTypeManager();
    $this->assertNull($entity_type_manager->getStorage('media')->loadUnchanged($media1->id()));
    // File1 is also in use in media3.
    $assert_session->pageTextContains('Could not delete 1 associated file because it is used in other places');
    $this->assertNotNull($entity_type_manager->getStorage('file')->loadUnchanged($file1->id()));

    $page->checkField('media_bulk_form[0]');
    $page->selectFieldOption('action', 'media_delete_action');
    $page->pressButton('Apply to selected items');
    $page->checkField('also_delete_file');
    $page->pressButton('Delete');
    $assert_session->pageTextContains('Deleted 1 item.');
    $this->assertNull($entity_type_manager->getStorage('media')->loadUnchanged($media2->id()));
    // The editor1 does not have permission to delete file created by editor2.
    $assert_session->pageTextContains('Could not delete 1 associated file because of insufficient privilege.');
    $this->assertNotNull($entity_type_manager->getStorage('file')->loadUnchanged($file2->id()));

    $this->drupalLogin($editor2);
    $this->drupalGet('test-media-bulk-form');
    $page->checkField('media_bulk_form[0]');
    $page->checkField('media_bulk_form[1]');
    $page->selectFieldOption('action', 'media_delete_action');
    $page->pressButton('Apply to selected items');
    $page->checkField('also_delete_file');
    $page->pressButton('Delete');
    $assert_session->pageTextContains('Deleted 2 items.');
    $this->assertNull($entity_type_manager->getStorage('media')->loadUnchanged($media3->id()));
    $this->assertNull($entity_type_manager->getStorage('media')->loadUnchanged($media4->id()));
    $assert_session->pageTextContains('Deleted 2 associated files.');
    // Usage count of file1 is now 0 because both media1 and 3 were deleted.
    $this->assertNull($entity_type_manager->getStorage('file')->loadUnchanged($file1->id()));
    // Editor2 can delete any file regardless of file owner.
    $this->assertNull($entity_type_manager->getStorage('file')->loadUnchanged($file3->id()));
  }

}
