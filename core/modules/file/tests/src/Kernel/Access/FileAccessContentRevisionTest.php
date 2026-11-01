<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Kernel\Access;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\KernelTests\Core\File\FileTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests access to managed files.
 *
 * @group file
 */
class FileAccessContentRevisionTest extends FileTestBase {

  use UserCreationTrait;

  /**
   * Created file entity.
   *
   * @var \Drupal\file\Entity\File
   */
  protected FileInterface $file;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'field',
    'user',
    'file_test',
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_rev');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['user', 'field', 'system']);

    FieldStorageConfig::create([
      'field_name' => 'file_test',
      'entity_type' => 'entity_test_rev',
      'type' => 'file',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_rev',
      'field_name' => 'file_test',
      'bundle' => 'entity_test_rev',
      'settings' => ['file_directory' => $this->getRandomGenerator()->name()],
    ])->save();

    $this->file = File::create([
      'uri' => 'private://example.txt',
    ]);
    file_put_contents($this->file->getFileUri(), $this->randomMachineName());
    $this->file->save();
  }

  /**
   * Tests the association of file access depending on revision of the content.
   */
  public function testFileAccess(): void {
    \Drupal::state()->set('file_test_alternate_entity_test_access_handler', TRUE);
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $user = $this->createUser(['view test entity']);
    $this->setCurrentUser($user);
    // Create a test entity with the file.
    $entity = EntityTestRev::create();
    $entity->file_test->target_id = $this->file->id();
    $entity->file_test->display = 1;
    $entity->name->value = $this->randomMachineName();
    $entity->save();

    $rev2 = clone $entity;
    $rev2->set('file_test', []);
    $rev2->setNewRevision(TRUE);
    $rev2->save();

    self::assertEquals($entity->get('file_test')->getValue(), [
      [
        'target_id' => $this->file->id(),
        'display' => 1,
      ],
    ]);
    self::assertEquals($rev2->get('file_test')->getValue(), []);

    self::assertTrue($rev2->access('view', $user));
    self::assertFalse($entity->access('view', $user));
    self::assertFalse($this->file->access('view', $user));
  }

}
