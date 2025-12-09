<?php

namespace Drupal\KernelTests\Core\Entity;

use Drupal\entity_test\Entity\EntityTest;

/**
 * Tests the Entity Change Notification System.
 *
 * @group Entity
 */
class EntityOnChangeTest extends EntityKernelTestBase {

  /**
   * Tests removing an item.
   */
  public function testRemove() {
    // Test on-change when creating an entity with initial values.
    $entity = EntityTest::create([
      'type' => 'entity_test',
      'langcode' => 'en',
    ]);
    $expected = [
      'langcode' => 1,
      'type' => 1,
      'user_id' => 1,
    ];
    $actual = $entity->getOnChangeRegister('en');
    asort($expected);
    asort($actual);
    $this->assertEquals($expected, $actual);

    // Test removing the item of one the fields.
    $field = $entity->get('user_id');
    $this->assertFalse($field->isEmpty());
    $entity->get('user_id')->removeItem(0);
    $this->assertTrue($field->isEmpty());
    $expected['user_id']++;
    $actual = $entity->getOnChangeRegister('en');
    asort($actual);
    $this->assertEquals($expected, $actual);
  }

}
