<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\entity_test_revlog\Entity\EntityTestMulWithRevisionLog;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the revision system.
 *
 * This test uses the entity_test_revlog module, which intentionally omits the
 * entity_metadata_keys fields. This causes deprecation errors.
 */
#[CoversClass(RevisionableContentEntityBase::class)]
#[Group('Entity')]
#[RunTestsInSeparateProcesses]
class RevisionableContentEntityBaseTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test_revlog', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test_mul_revlog');
  }

  /**
   * Tests the correct functionality CRUD operations of entity revisions.
   */
  public function testRevisionableContentEntity(): void {
    $entity_type = 'entity_test_mul_revlog';
    $definition = \Drupal::entityTypeManager()->getDefinition($entity_type);
    $user = User::create(['name' => 'test name']);
    $user->save();
    /** @var \Drupal\entity_test_revlog\Entity\EntityTestMulWithRevisionLog $entity */
    $entity = EntityTestMulWithRevisionLog::create([
      'type' => $entity_type,
    ]);

    // Save the entity, this creates the first revision.
    $entity->save();
    $revision_ids[] = $entity->getRevisionId();
    $this->assertItemsTableCount(1, $definition);

    // Create the second revision.
    $entity->setNewRevision(TRUE);
    $random_timestamp = rand(100_000_000, 200_000_000);
    $this->createRevision($entity, $user, $random_timestamp, 'This is my log message');

    $revision_id = $entity->getRevisionId();
    $revision_ids[] = $revision_id;

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('entity_test_mul_revlog');
    $entity = $storage->loadRevision($revision_id);
    $this->assertEquals($random_timestamp, $entity->getRevisionCreationTime());
    $this->assertEquals($user->id(), $entity->getRevisionUserId());
    $this->assertEquals($user->id(), $entity->getRevisionUser()->id());
    $this->assertEquals('This is my log message', $entity->getRevisionLogMessage());

    // Create the third revision.
    $random_timestamp = rand(100_000_000, 200_000_000);
    $this->createRevision($entity, $user, $random_timestamp, 'This is my log message');
    $this->assertItemsTableCount(3, $definition);
    $revision_ids[] = $entity->getRevisionId();

    // Create another 3 revisions.
    foreach (range(1, 3) as $count) {
      $timestamp = rand(100_000_000, 200_000_000);
      $this->createRevision($entity, $user, $timestamp, 'This is my log message number: ' . $count);
      $revision_ids[] = $entity->getRevisionId();
    }
    $this->assertItemsTableCount(6, $definition);

    $this->assertCount(6, $revision_ids);

    // Delete the first 3 revisions.
    foreach (range(0, 2) as $key) {
      $storage->deleteRevision($revision_ids[$key]);
    }

    // We should have only data for three revisions.
    $this->assertItemsTableCount(3, $definition);
  }

  /**
   * Tests the behavior of the "revision_default" flag.
   *
   * @legacy-covers \Drupal\Core\Entity\ContentEntityBase::wasDefaultRevision
   */
  public function testWasDefaultRevision(): void {
    $entity_type_id = 'entity_test_mul_revlog';
    $entity = EntityTestMulWithRevisionLog::create([
      'type' => $entity_type_id,
    ]);

    // Checks that in a new entity ::wasDefaultRevision() always matches
    // ::isDefaultRevision().
    $this->assertEquals($entity->isDefaultRevision(), $entity->wasDefaultRevision());
    $entity->isDefaultRevision(FALSE);
    $this->assertEquals($entity->isDefaultRevision(), $entity->wasDefaultRevision());

    // Check that a new entity is always flagged as a default revision on save,
    // regardless of its default revision status.
    $entity->save();
    $this->assertTrue($entity->wasDefaultRevision());

    // Check that a pending revision is not flagged as default.
    $entity->setNewRevision();
    $entity->isDefaultRevision(FALSE);
    $entity->save();
    $this->assertFalse($entity->wasDefaultRevision());

    // Check that a default revision is flagged as such.
    $entity->setNewRevision();
    $entity->isDefaultRevision(TRUE);
    $entity->save();
    $this->assertTrue($entity->wasDefaultRevision());

    // Check that a manually set value for the "revision_default" flag is
    // ignored on save.
    $entity->setNewRevision();
    $entity->isDefaultRevision(FALSE);
    $entity->set('revision_default', TRUE);
    $this->assertTrue($entity->wasDefaultRevision());
    $entity->save();
    $this->assertFalse($entity->wasDefaultRevision());

    // Check that the default revision status was stored correctly.
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    foreach ([TRUE, FALSE, TRUE, FALSE] as $index => $expected) {
      /** @var \Drupal\entity_test_revlog\Entity\EntityTestMulWithRevisionLog $revision */
      $revision = $storage->loadRevision($index + 1);
      $this->assertEquals($expected, $revision->wasDefaultRevision());
    }

    // Check that the default revision is flagged correctly.
    /** @var \Drupal\entity_test_revlog\Entity\EntityTestMulWithRevisionLog $entity */
    $entity = $storage->loadUnchanged($entity->id());
    $this->assertTrue($entity->wasDefaultRevision());

    // Check that the "revision_default" flag cannot be changed once set.
    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessageIs("An existing default revision of the 'entity_test_mul_revlog' entity type can not be changed to a non-default revision.");
    /** @var \Drupal\entity_test_revlog\Entity\EntityTestMulWithRevisionLog $entity2 */
    $entity2 = EntityTestMulWithRevisionLog::create([
      'type' => $entity_type_id,
    ]);
    $entity2->save();
    $this->assertTrue($entity2->wasDefaultRevision());
    $entity2->isDefaultRevision(FALSE);
    $entity2->save();
    $this->assertTrue($entity2->wasDefaultRevision());
  }

  /**
   * Tests resaving a previous default revision while syncing.
   */
  public function testResaveFormerDefaultRevisionWhileSyncing(): void {
    $definition = $this->entityTypeManager->getDefinition('entity_test_mul_revlog');
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('entity_test_mul_revlog');

    $entity = EntityTestMulWithRevisionLog::create([
      'type' => 'entity_test_mul_revlog',
      'name' => 'first',
    ]);
    $entity->save();
    $firstRevisionId = $entity->getRevisionId();
    $entity->setNewRevision();
    $entity->setName('second');
    $entity->save();
    $secondRevisionId = $entity->getRevisionId();

    // The first revision was the default revision when it was created, but no
    // longer is.
    /** @var \Drupal\entity_test_revlog\Entity\EntityTestMulWithRevisionLog $firstRevision */
    $firstRevision = $storage->loadRevision($firstRevisionId);
    $this->assertTrue($firstRevision->wasDefaultRevision());
    $this->assertFalse($firstRevision->isDefaultRevision());

    // Resaving it with itself as the original revision is not allowed outside
    // of a sync. This mirrors \Drupal\node\NodeBulkUpdate::processNode().
    $firstRevision->setOriginal(clone $firstRevision);
    $firstRevision->setName('first updated');
    try {
      $firstRevision->save();
      $this->fail('Resaving a former default revision without syncing throws an exception.');
    }
    catch (EntityStorageException $e) {
      $this->assertSame("An existing default revision of the 'entity_test_mul_revlog' entity type can not be changed to a non-default revision.", $e->getMessage());
    }

    // Resaving it while syncing is allowed.
    $firstRevision = $storage->loadRevision($firstRevisionId);
    $firstRevision->setOriginal(clone $firstRevision);
    $firstRevision->setName('first updated');
    $firstRevision->setSyncing(TRUE);
    $firstRevision->save();

    // The first revision is updated in place and keeps its flags.
    $firstRevision = $storage->loadRevisionUnchanged($firstRevisionId);
    $this->assertEquals('first updated', $firstRevision->label());
    $this->assertTrue($firstRevision->wasDefaultRevision());
    $this->assertFalse($firstRevision->isDefaultRevision());

    // The default revision and the data table are unchanged.
    $entity = $storage->loadUnchanged($entity->id());
    $this->assertEquals($secondRevisionId, $entity->getRevisionId());
    $this->assertEquals('second', $entity->label());
    $this->assertItemsTableCount(2, $definition);
  }

  /**
   * Asserts the amount of items on entity related tables.
   *
   * @param int $count
   *   The number of items expected to be in revisions related tables.
   * @param \Drupal\Core\Entity\EntityTypeInterface $definition
   *   The definition and metadata of the entity being tested.
   *
   * @internal
   */
  protected function assertItemsTableCount(int $count, EntityTypeInterface $definition): void {
    $connection = Database::getConnection();
    $this->assertEquals(1, (int) $connection->select($definition->getBaseTable())->countQuery()->execute()->fetchField());
    $this->assertEquals(1, (int) $connection->select($definition->getDataTable())->countQuery()->execute()->fetchField());
    $this->assertEquals($count, (int) $connection->select($definition->getRevisionTable())->countQuery()->execute()->fetchField());
    $this->assertEquals($count, (int) $connection->select($definition->getRevisionDataTable())->countQuery()->execute()->fetchField());

  }

  /**
   * Creates a new revision in the entity of this test class.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity where revision will be created.
   * @param \Drupal\user\UserInterface $user
   *   The author of the new revision.
   * @param int $timestamp
   *   The timestamp of the new revision.
   * @param string $log_message
   *   The log message of the new revision.
   */
  protected function createRevision(EntityInterface $entity, UserInterface $user, $timestamp, $log_message): void {
    $entity->setNewRevision(TRUE);
    $entity->setRevisionCreationTime($timestamp);
    $entity->setRevisionUserId($user->id());
    $entity->setRevisionLogMessage($log_message);
    $entity->save();
  }

}
