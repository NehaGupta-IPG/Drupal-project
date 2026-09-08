<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel;

use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\Tests\media\Kernel\MediaKernelTestBase;
use Drupal\user\CancellationHandlerInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests revision-safe cancellation for media and files.
 */
#[Group('user')]
#[Group('media')]
#[RunTestsInSeparateProcesses]
final class RevisionSafeCancellationMediaTest extends MediaKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'image',
    'media',
    'media_test_source',
    'system',
    'user',
  ];

  /**
   * Tests that media revisions and files are reassigned independently.
   */
  public function testReassignMediaAndFileAuthorship(): void {
    $source = $this->user;
    $current_editor = User::create(['name' => 'current-editor', 'status' => 1]);
    $current_editor->save();

    $media = $this->generateMedia('user-cancellation.txt', $this->testMediaType);
    $media->setOwnerId($source->id());
    $media->setRevisionUserId($source->id());
    $media->save();
    $old_revision_id = $media->getRevisionId();

    $media->setNewRevision(TRUE);
    $media->setName('Current media name');
    $media->setOwnerId($source->id());
    $media->setRevisionUserId($current_editor->id());
    $media->save();
    $current_revision_id = $media->getRevisionId();
    $revision_count = $this->countMediaRevisions();

    $file = File::create([
      'filename' => 'user-cancellation.txt',
      'uri' => 'public://user-cancellation.txt',
      'uid' => $source->id(),
    ]);
    $file->save();

    $this->cancelForEntityType('media', $source, CancellationHandlerInterface::METHOD_REASSIGN);
    $this->cancelForEntityType('file', $source, CancellationHandlerInterface::METHOD_REASSIGN);

    $media_storage = $this->container->get('entity_type.manager')->getStorage('media');
    $media_storage->resetCache();
    /** @var \Drupal\media\MediaInterface $current */
    $current = $media_storage->load($media->id());
    /** @var \Drupal\media\MediaInterface $old_revision */
    $old_revision = $media_storage->loadRevision($old_revision_id);
    $file_storage = $this->container->get('entity_type.manager')->getStorage('file');
    $file_storage->resetCache([$file->id()]);
    /** @var \Drupal\file\FileInterface $reloaded_file */
    $reloaded_file = $file_storage->load($file->id());

    self::assertSame($current_revision_id, $current->getRevisionId());
    self::assertSame('Current media name', $current->getName());
    self::assertSame('0', $current->getOwnerId());
    self::assertSame('0', $old_revision->getOwnerId());
    self::assertSame('0', $old_revision->getRevisionUserId());
    self::assertSame($current_editor->id(), $current->getRevisionUserId());
    self::assertSame('0', $reloaded_file->getOwnerId());
    self::assertSame($revision_count, $this->countMediaRevisions());
  }

  /**
   * Tests that reassignment does not remap historical revision metadata.
   */
  public function testReassignPreservesHistoricalMediaMetadata(): void {
    $source = $this->user;
    $current_owner = User::create([
      'name' => 'current-owner',
      'status' => 1,
    ]);
    $current_owner->save();

    $mapped_field = 'field_mapped_metadata';
    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => $mapped_field,
      'type' => 'string',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $this->testMediaType->id(),
      'label' => 'Mapped metadata',
    ])->save();
    $this->testMediaType->setFieldMap([
      'attribute_1' => $mapped_field,
    ])->save();

    \Drupal::state()->set('media_source_test_attributes', [
      'attribute_1' => [
        'label' => 'Attribute 1',
        'value' => 'Historical metadata',
      ],
    ]);
    $media = Media::create([
      'bundle' => $this->testMediaType->id(),
      'name' => 'Historical media',
      'field_media_test' => 'historical-source',
      'uid' => $source->id(),
    ]);
    $media->setRevisionUserId($source->id());
    $media->save();
    $historical_revision_id = $media->getRevisionId();

    \Drupal::state()->set('media_source_test_attributes', [
      'attribute_1' => [
        'label' => 'Attribute 1',
        'value' => 'Current metadata',
      ],
    ]);
    $media->setNewRevision(TRUE);
    $media->setName('Current media');
    $media->set('field_media_test', 'current-source');
    $media->setOwnerId($current_owner->id());
    $media->setRevisionUserId($current_owner->id());
    $media->save();
    $revision_count = $this->countMediaRevisions();

    $this->cancelForEntityType(
      'media',
      $source,
      CancellationHandlerInterface::METHOD_REASSIGN,
    );

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('media');
    $storage->resetCache();
    /** @var \Drupal\media\MediaInterface $historical */
    $historical = $storage->loadRevision($historical_revision_id);
    /** @var \Drupal\media\MediaInterface $current */
    $current = $storage->load($media->id());

    self::assertSame('0', $historical->getOwnerId());
    self::assertSame(
      'Historical metadata',
      $historical->get($mapped_field)->value,
    );
    self::assertSame('Current metadata', $current->get($mapped_field)->value);

    // The stored historical revision remains the comparison point when a
    // caller deliberately changes its source during synchronization.
    \Drupal::state()->set('media_source_test_attributes', [
      'attribute_1' => [
        'label' => 'Attribute 1',
        'value' => 'Updated historical metadata',
      ],
    ]);
    $historical->set('field_media_test', 'updated-historical-source');
    $historical->setSyncing(TRUE);
    $historical->save();
    $storage->resetCache();
    $historical = $storage->loadRevision($historical_revision_id);
    $current = $storage->load($media->id());

    self::assertSame(
      'Updated historical metadata',
      $historical->get($mapped_field)->value,
    );
    self::assertSame('Current metadata', $current->get($mapped_field)->value);
    self::assertSame($revision_count, $this->countMediaRevisions());
  }

  /**
   * Tests that BLOCK_UNPUBLISH does not affect File (no "published" key).
   */
  public function testUnpublishDoesNotAffectFilesWithoutPublishedKey(): void {
    $source = $this->user;

    $file = File::create([
      'filename' => 'user-cancellation.txt',
      'uri' => 'public://user-cancellation.txt',
      'uid' => $source->id(),
    ]);
    $file->save();

    $this->cancelForEntityType('file', $source, CancellationHandlerInterface::METHOD_BLOCK_UNPUBLISH);

    $file_storage = $this->container->get('entity_type.manager')->getStorage('file');
    $file_storage->resetCache([$file->id()]);
    $reloaded = $file_storage->load($file->id());

    self::assertInstanceOf(FileInterface::class, $reloaded);
    self::assertSame($source->id(), $reloaded->getOwnerId());
  }

  /**
   * Tests that block-unpublish updates only the current media revision.
   */
  public function testUnpublishMediaPreservesRevisionHistory(): void {
    $source = $this->user;
    $media = $this->generateMedia(
      'unpublish-me.txt',
      $this->testMediaType,
    );
    $media->setOwnerId($source->id());
    $media->save();
    $old_revision_id = $media->getRevisionId();

    $media->setNewRevision(TRUE);
    $media->setName('Current media name');
    $media->save();
    $current_revision_id = $media->getRevisionId();
    $revision_count = $this->countMediaRevisions();

    $this->cancelForEntityType(
      'media',
      $source,
      CancellationHandlerInterface::METHOD_BLOCK_UNPUBLISH,
    );

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('media');
    $storage->resetCache([$media->id()]);
    /** @var \Drupal\media\MediaInterface $current */
    $current = $storage->load($media->id());
    /** @var \Drupal\media\MediaInterface $old_revision */
    $old_revision = $storage->loadRevision($old_revision_id);

    self::assertSame($current_revision_id, $current->getRevisionId());
    self::assertFalse($current->isPublished());
    self::assertTrue($old_revision->isPublished());
    self::assertSame($revision_count, $this->countMediaRevisions());
  }

  /**
   * Tests that delete cancellation preserves shared media and files.
   */
  public function testDeletePreservesSharedMediaAndFiles(): void {
    $source = $this->user;
    $current_owner = User::create(['name' => 'current-owner', 'status' => 1]);
    $current_owner->save();
    $media = $this->generateMedia('delete-me.txt', $this->testMediaType);
    $media->setOwnerId($source->id());
    $media->save();
    $retained_media = $this->generateMedia('keep-me.txt', $this->testMediaType);
    $retained_media->setOwnerId($source->id());
    $retained_media->setRevisionUserId($source->id());
    $retained_media->save();
    $owned_revision_id = $retained_media->getRevisionId();
    $retained_media->setNewRevision(TRUE);
    $retained_media->setOwnerId($current_owner->id());
    $retained_media->setRevisionUserId($current_owner->id());
    $retained_media->save();
    $shared_file_ids = $this->container->get('entity_type.manager')
      ->getStorage('file')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('filename', 'keep-me.txt')
      ->execute();
    /** @var \Drupal\file\FileInterface $shared_file */
    $shared_file = File::load(reset($shared_file_ids));
    $this->container->get('file.usage')->add(
      $shared_file,
      'file',
      'media',
      $retained_media->id(),
    );
    $file = File::create([
      'filename' => 'delete-me.txt',
      'uri' => 'public://delete-me.txt',
      'uid' => $source->id(),
    ]);
    $file->save();

    $this->cancelForEntityType('media', $source, CancellationHandlerInterface::METHOD_DELETE);
    $this->cancelForEntityType('file', $source, CancellationHandlerInterface::METHOD_DELETE);

    $media_storage = $this->container->get('entity_type.manager')->getStorage('media');
    $media_storage->resetCache();
    $file_storage = $this->container->get('entity_type.manager')->getStorage('file');
    $file_storage->resetCache([$file->id(), $shared_file->id()]);
    $reloaded_deleted_media = $media_storage->load($media->id());
    self::assertNotNull($reloaded_deleted_media);
    self::assertSame('0', $reloaded_deleted_media->getOwnerId());
    $reloaded_media = $media_storage->load($retained_media->id());
    self::assertNotNull($reloaded_media);
    self::assertSame($current_owner->id(), $reloaded_media->getOwnerId());
    self::assertSame(
      '0',
      $media_storage->loadRevision($owned_revision_id)->getOwnerId(),
    );
    self::assertSame('0', $file_storage->load($file->id())->getOwnerId());
    $reloaded_shared_file = $file_storage->load($shared_file->id());
    self::assertNotNull($reloaded_shared_file);
    self::assertSame('0', $reloaded_shared_file->getOwnerId());
    self::assertFileExists($reloaded_shared_file->getFileUri());
    self::assertNotEmpty(
      $this->container->get('file.usage')->listUsage($reloaded_shared_file),
    );
  }

  /**
   * Invokes the cancellation handler for an entity type.
   */
  private function cancelForEntityType(string $entity_type_id, UserInterface $account, string $method): void {
    $handler = $this->container->get('entity_type.manager')->getHandler($entity_type_id, 'user_cancel');
    self::assertInstanceOf(CancellationHandlerInterface::class, $handler);
    $handler->cancelAccount($account, $method);
  }

  /**
   * Counts all media revisions.
   */
  private function countMediaRevisions(): int {
    return count($this->container->get('entity_type.manager')
      ->getStorage('media')
      ->getQuery()
      ->allRevisions()
      ->accessCheck(FALSE)
      ->execute());
  }

}
