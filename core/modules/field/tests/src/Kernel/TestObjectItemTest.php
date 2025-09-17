<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests the serialization of an object.
 *
 * @todo Remove this test in Drupal 12.
 * @see https://www.drupal.org/node/3484452
 *
 * @group field
 * @group legacy
 */
class TestObjectItemTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a 'test_field' field and storage for validation.
    FieldStorageConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'entity_test',
      'type' => 'test_object_field',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_test',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Tests the serialization of a field type that has an object.
   */
  public function testTestObjectItem(): void {
    $object = new \stdClass();
    $object->foo = 'bar';
    $entity = EntityTest::create();
    $entity->field_test->value = $object;
    $this->expectDeprecation('Unserializing PHP objects from storage is deprecated in drupal:11.2.0 and forbidden in drupal:12.0.0. Consider using JSON or plain PHP array serialized data and hydrating the target object in code. See https://www.drupal.org/node/3484452');
    $entity->save();

    // Verify that the entity has been created properly.
    $id = $entity->id();
    $entity = EntityTest::load($id);
    $this->assertInstanceOf(\stdClass::class, $entity->field_test->value);
    $this->assertEquals($object, $entity->field_test->value);
  }

}
