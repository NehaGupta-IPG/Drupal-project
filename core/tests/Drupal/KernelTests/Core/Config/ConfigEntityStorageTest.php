<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Config;

use Drupal\Core\Config\ConfigDuplicateUUIDException;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests configuration entity storage.
 */
#[Group('config')]
#[RunTestsInSeparateProcesses]
class ConfigEntityStorageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_test'];

  /**
   * Tests creating configuration entities with changed UUIDs.
   */
  public function testUUIDConflict(): void {
    $entity_type = 'config_test';
    $id = 'test_1';
    // Load the original configuration entity.
    $storage = $this->container->get('entity_type.manager')
      ->getStorage($entity_type);
    $storage->create(['id' => $id])->save();
    $entity = $storage->load($id);

    $original_properties = $entity->toArray();

    // Override with a new UUID and try to save.
    $new_uuid = $this->container->get('uuid')->generate();
    $entity->set('uuid', $new_uuid);

    try {
      $entity->save();
      $this->fail('Exception thrown when attempting to save a configuration entity with a UUID that does not match the existing UUID.');
    }
    catch (ConfigDuplicateUUIDException) {
      // Expected exception; just continue testing.
    }

    // Ensure that the config entity was not corrupted.
    $entity = $storage->loadUnchanged($entity->id());
    $this->assertSame($original_properties, $entity->toArray());
  }

  /**
   * Test that the compilation of a query condition works as expected.
   */
  public function testSavingEmptyUuid(): void {
    $entity_type = 'config_test';
    $id = 'XFiles_S06E11_Two_Fathers';
    // Load the original configuration entity.
    $storage = $this->container->get('entity_type.manager')
      ->getStorage($entity_type);
    $entity = $storage->create(['id' => $id]);
    $entity->save();
    $entity->set('uuid', NULL);
    try {
      $entity->save();
    }
    catch (ConfigDuplicateUUIDException) {
      // We totally expect that to happen when we remove the uuid like that.
    }
    // Make sure we assert something as well then.
    self::assertEquals($id, $entity->id());
  }

  /**
   * Tests the hasData() method for config entity storage.
   *
   * @legacy-covers \Drupal\Core\Config\Entity\ConfigEntityStorage::hasData
   */
  public function testHasData(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('config_test');
    $this->assertFalse($storage->hasData());

    // Add a test config entity and check again.
    $storage->create(['id' => $this->randomMachineName()])->save();
    $this->assertTrue($storage->hasData());
  }

}
