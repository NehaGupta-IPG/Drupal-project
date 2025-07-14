<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Form\MediaDeleteForm;
use Drupal\media\Form\MediaDeleteMultipleForm;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createMediaType('image', [
      'id' => 'image',
      'new_revision' => TRUE,
    ]);
  }

  /**
   * Tests deleting the file associated with a media item.
   */
  public function testDeleteFileAssociatedWithMedia(): void {
    [, $image2] = $this->getTestFiles('image');
    $this->assertIsObject($image2);
    $this->assertObjectHasProperty('uri', $image2);

    $editor1 = $this->createUser([
      "delete any image media",
      'delete own files',
    ]);
    $editor2 = $this->createUser([
      "delete any image media",
    ]);
    $editor3 = $this->createUser([
      "delete any image media",
      'delete any file',
    ]);

    $media1 = $this->createMedia($editor1);
    $file1 = $media1->field_media_image->entity;

    $file2 = File::create([
      'uri' => $image2->uri,
      'uid' => $editor2->id(),
    ]);
    $file2->save();
    $media2 = Media::create([
      'bundle' => 'image',
      'name' => $this->randomMachineName(),
      'field_media_image' => $file2,
    ]);
    $media2->save();
    $media3 = Media::create([
      'bundle' => 'image',
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
    $common_permissions = [
      'delete any image media',
      'access media overview',
      'view media',
    ];
    $editor1 = $this->createUser(['delete own files', ...$common_permissions]);
    $editor2 = $this->createUser(['delete any file', ...$common_permissions]);

    $media1 = $this->createMedia($editor1);
    $file1 = $media1->field_media_image->entity;

    $media2 = $this->createMedia($editor2);
    $file2 = $media2->field_media_image->entity;

    $media3 = Media::create([
      'bundle' => 'image',
      'name' => $this->randomMachineName(),
      'field_media_image' => $file1,
    ]);
    $media3->save();

    $media4 = $this->createMedia($editor1);
    $file3 = $media4->field_media_image->entity;

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->drupalLogin($editor1);
    $this->drupalGet('test-media-bulk-form');
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

  /**
   * Tests the delete file default setting on a single media entity.
   */
  #[TestWith([TRUE])]
  #[TestWith([FALSE])]
  public function testDeleteFileDefaultOnSingleMediaDeletion(bool $delete): void {
    $account = $this->createUser(admin: TRUE);
    $this->drupalLogin($account);

    $this->config('media.settings')
      ->set('delete_file_default', $delete)
      ->save();
    $media = $this->createMedia();

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Go to media deletion form and check default setting is FALSE:
    $this->drupalGet($media->toUrl('delete-form'));
    $assert_session->statusCodeEquals(200);
    $this->assertSame($delete, $assert_session->fieldExists('also_delete_file')->isChecked());
    // Delete media entity and check if file still exists.
    $page->pressButton('Delete');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('The media item ' . $media->getName() . ' has been deleted.');
    $this->assertSame(!$delete, file_exists($media->field_media_image->entity->getFileUri()));

    if ($delete) {
      $assert_session->pageTextContains('Deleted the associated file ' . $media->field_media_image->entity->getFilename() . '.');
    }
  }

  /**
   * Tests the delete file default setting on multiple media entities.
   */
  #[TestWith([TRUE])]
  #[TestWith([FALSE])]
  public function testDeleteFileDefaultOnMultipleMediaDeletion(bool $delete): void {
    $account = $this->createUser(admin: TRUE);
    $this->drupalLogin($account);

    $this->config('media.settings')
      ->set('delete_file_default', $delete)
      ->save();

    $media1 = $this->createMedia();
    $media2 = $this->createMedia();

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // The bulk deletion form should have the checkbox unchecked by default.
    $this->drupalGet('test-media-bulk-form');
    $assert_session->statusCodeEquals(200);
    $page->checkField('edit-media-bulk-form-0');
    $page->checkField('edit-media-bulk-form-1');
    $page->selectFieldOption('action', 'media_delete_action');
    $page->pressButton('Apply to selected items');
    $this->assertSame($delete, $assert_session->fieldExists('also_delete_file')->isChecked());

    $page->pressButton('Delete');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Deleted 2 items.');
    $this->assertSame(!$delete, file_exists($media1->field_media_image->entity->getFileUri()));
    $this->assertSame(!$delete, file_exists($media2->field_media_image->entity->getFileUri()));
  }

  /**
   * Tests the disable user control setting on a single media entity.
   */
  public function testDisableDeleteControlOnSingleMediaEntityDeletion(): void {
    $account = $this->createUser(admin: TRUE);
    $this->drupalLogin($account);

    $assert_session = $this->assertSession();
    // The checkbox should be present on the media deletion form.
    $this->drupalGet($this->createMedia()->toUrl('delete-form'));
    $assert_session->fieldExists('also_delete_file');

    $this->config('media.settings')
      ->set('disable_delete_control', TRUE)
      ->save();

    // With the setting changed, the checkbox should be absent.
    $this->getSession()->reload();
    $assert_session->statusCodeEquals(200);
    $assert_session->fieldNotExists('also_delete_file');
  }

  /**
   * Tests the disable user control setting on a multiple media entities.
   */
  public function testDisableDeleteControlOnMultipleMediaEntityDeletion(): void {
    $account = $this->createUser(admin: TRUE);
    $this->drupalLogin($account);

    $this->createMedia();
    $this->createMedia();

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // The deletion checkbox should be present on the bulk deletion form.
    $this->drupalGet('test-media-bulk-form');
    $page->checkField('edit-media-bulk-form-0');
    $page->checkField('edit-media-bulk-form-1');
    $page->selectFieldOption('action', 'media_delete_action');
    $page->pressButton('Apply to selected items');
    $assert_session->statusCodeEquals(200);
    $assert_session->fieldExists('also_delete_file');

    $this->config('media.settings')
      ->set('disable_delete_control', TRUE)
      ->save();

    // The bulk deletion form should not longer have the checkbox.
    $this->drupalGet('test-media-bulk-form');
    $assert_session->statusCodeEquals(200);
    $page->checkField('edit-media-bulk-form-0');
    $page->checkField('edit-media-bulk-form-1');
    $page->selectFieldOption('action', 'media_delete_action');
    $page->pressButton('Apply to selected items');
    $assert_session->statusCodeEquals(200);
    $assert_session->fieldNotExists('also_delete_file');
  }

  /**
   * Creates a media entity from an image file.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $file_owner
   *   (optional) The user account which should own the file.
   *
   * @return \Drupal\media\Entity\Media
   *   The created media entity.
   */
  private function createMedia(?AccountInterface $file_owner): Media {
    static $test_files;
    $test_files ??= $this->getTestFiles('image');
    $this->assertNotEmpty($test_files);

    static $which = 0;
    if ($which > count($test_files)) {
      $which = 0;
    }
    $this->assertIsObject($test_files[$which]);
    $this->assertObjectHasProperty('uri', $test_files[$which]);

    $file = File::create([
      'uri' => $test_files[$which]->uri,
    ]);
    if ($file_owner) {
      $file->setOwner($file_owner);
    }
    $file->save();

    $media = Media::create([
      'bundle' => 'image',
      'name' => $this->randomMachineName(),
      'field_media_image' => $file,
    ]);
    $media->save();

    // Sanity check.
    $this->assertFileExists($media->field_media_image->entity->getFileUri());

    return $media;
  }

}
