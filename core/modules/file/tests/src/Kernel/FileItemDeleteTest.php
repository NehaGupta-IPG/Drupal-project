<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Kernel;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;

/**
 * Tests the file field item delete function.
 *
 * @group file
 */
class FileItemDeleteTest extends FileItemTest {

  /**
   * Tests the saving of entity when referenced file is deleted.
   */
  public function testFileItemDelete(): void {
    $field_definition = FieldConfig::load('entity_test.entity_test.file_test');
    $handler_id = $field_definition->getSetting('handler');
    $this->assertEquals('default:file', $handler_id);

    // Create a test entity with the
    $entity = EntityTest::create();
    $entity->file_test->target_id = $this->file->id();
    $entity->file_test->display = 1;
    $entity->file_test->description = $description = $this->randomMachineName();
    $entity->name->value = $this->randomMachineName();
    $entity->save();

    $entity = EntityTest::load($entity->id());
    $this->assertInstanceOf(FieldItemListInterface::class, $entity->file_test);
    $this->assertInstanceOf(FieldItemInterface::class, $entity->file_test[0]);
    $this->assertEquals($this->file->id(), $entity->file_test->target_id);

    $this->file->delete();

    $entity = EntityTest::load($entity->id());
    self::assertEquals(SAVED_UPDATED, $entity->save());
  }

}
