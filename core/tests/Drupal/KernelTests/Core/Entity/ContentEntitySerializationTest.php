<?php

namespace Drupal\KernelTests\Core\Entity;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the serialization of content entities.
 *
 * @group Entity
 */
class ContentEntitySerializationTest extends EntityKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['user', 'system', 'field', 'entity_test', 'language'];

  /**
   * The entity type id.
   *
   * @var string
   */
  protected $entityTypeId = 'entity_test_mul';

  /**
   * The EntityTestMul entity type storage.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $entityStorage;

  /**
   * The entity reference field name.
   *
   * @var string
   */
  protected $entityReferenceFieldName = 'field_test_entity_reference';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema($this->entityTypeId);
    $this->installConfig('language');
    $this->entityStorage = $this->entityTypeManager->getStorage($this->entityTypeId);

    // Create the test entity reference field.
    FieldStorageConfig::create([
      'field_name' => $this->entityReferenceFieldName,
      'entity_type' => $this->entityTypeId,
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => $this->entityTypeId,
      ],
    ])->save();
    FieldConfig::create([
      'entity_type' => $this->entityTypeId,
      'field_name' => $this->entityReferenceFieldName,
      'bundle' => $this->entityTypeId,
      'translatable' => TRUE,
    ])->save();
  }

  /**
   * Tests regular and deep serialization.
   */
  public function testSerialization() {
    $this->doTestSerialization(FALSE);
    $this->doTestSerialization(TRUE);
  }

  /**
   * Tests regular or deep serialization based on the parameter.
   *
   * @param bool $deep_serialization
   *   Whether to test regular or deep serialization.
   */
  protected function doTestSerialization($deep_serialization) {
    $field_name = $this->entityReferenceFieldName;
    $user = $this->createUser();
    $initial_entity_name = 'test test';

    // Create the test entities.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity_level_zero */
    $entity_level_zero = $this->entityStorage->create([
      'name' => $initial_entity_name,
      'user_id' => $user->id(),
      'language' => 'en',
    ]);
    $entity_level_zero->save();
    $entity_level_one = $entity_level_zero->createDuplicate();
    $entity_level_one->save();
    $entity_level_two = $entity_level_zero->createDuplicate();
    $entity_level_two->save();

    $entity_level_zero->$field_name->appendItem($entity_level_one);
    $entity_level_zero->save();
    $entity_level_one->$field_name->appendItem($entity_level_two);
    $entity_level_one->save();

    // Change the entity values in memory but do not persist them into the
    // storage.
    $entity_level_zero->name = 'entity level zero';
    $entity_level_zero->$field_name->entity->name = 'entity level one';
    $entity_level_zero->$field_name->entity->$field_name->entity->name = 'entity level two';

    // Alter a computed property beside the entity property in order to assure
    // that not all computed properties are being serialized through deep
    // serialization but only computed properties of entity reference fields.
    $entity_type = $entity_level_two->getEntityType();
    $this->assertTrue($entity_type->hasKey('langcode'));
    $langcode_field_name = $entity_type->getKey('langcode');
    $langcode_field = $entity_level_zero->$field_name->entity->$field_name->entity->$langcode_field_name;
    $test_language = ConfigurableLanguage::load($langcode_field->language->getId());
    $original_language_name = $test_language->getName();
    $changed_language_name = $original_language_name . ' changed';
    $test_language->setName($changed_language_name);
    $langcode_field->language = $test_language;
    $this->assertEquals($changed_language_name, $langcode_field->language->getName());

    if ($deep_serialization) {
      $entity_level_zero->setDeepSerialization(TRUE);
    }

    // Serialize and then unserialize the entity in order to check that the
    // entity has been properly serialized depending on the used serialization
    // method - regular or deep serialization.
    $entity_level_zero = unserialize(serialize($entity_level_zero));

    // If regular serialization is tested then the changes on all computed
    // property values should be lost. If deep serialization is tested then the
    // changes only on computed properties of entity reference fields should be
    // kept, but changes on computed properties of other fields should be lost.
    $this->assertEquals('entity level zero', $entity_level_zero->label());
    $this->assertEquals($deep_serialization ? 'entity level one' : $initial_entity_name, $entity_level_zero->$field_name->entity->label());
    $this->assertEquals($deep_serialization ? 'entity level two' : $initial_entity_name, $entity_level_zero->$field_name->entity->$field_name->entity->label());
    $language = $entity_level_zero->$field_name->entity->$field_name->entity->$langcode_field_name->language;
    $this->assertEquals($original_language_name, $language->getName());
  }

  /**
   * Tests deep serialization with circular entity references.
   */
  public function testDeepSerializationWithCircularEntityReferences() {
    $field_name = $this->entityReferenceFieldName;
    $user = $this->createUser();

    // Case 1: new_entity_a->new_entity_b->new_entity_a
    /** @var \Drupal\entity_test\Entity\EntityTestMul $entity_level_zero */
    $entity_level_zero = $this->entityStorage->create([
      'name' => 'entity zero',
      'user_id' => $user->id(),
      'language' => 'en',
    ]);
    $entity_level_one = $entity_level_zero->createDuplicate();
    $entity_level_one->setName('entity one');

    // Create a circular reference, where the parent entity is referenced at the
    // end of the chain back again with the same object reference as at the
    // beginning of the chain.
    $entity_level_zero->$field_name->appendItem($entity_level_one);
    $entity_level_one->$field_name->appendItem($entity_level_zero);

    // Make sure all the entities are initialized in the structure, otherwise
    // they will not be contained in the serialization.
    $entity_level_zero->$field_name->entity->$field_name->entity;

    // Now serialize the entity structure through deep serialization.
    $entity_level_zero->setDeepSerialization(TRUE);
    $serialized_main_entity = serialize($entity_level_zero);
    $this->assertEquals(1, substr_count($serialized_main_entity, 'entity zero'));
    $this->assertEquals(1, substr_count($serialized_main_entity, 'entity one'));

    // Case 2: existing_entity_a->existing_entity_b->existing_entity_a_cloned
    /** @var \Drupal\entity_test\Entity\EntityTestMul $entity_level_zero */
    $entity_level_zero = $this->entityStorage->create([
      'name' => 'entity zero',
      'user_id' => $user->id(),
      'language' => 'en',
    ]);
    $entity_level_zero->save();
    $entity_level_one = $entity_level_zero->createDuplicate();
    $entity_level_one->setName('entity one');
    $entity_level_one->save();

    // Create a circular reference, where the parent entity is referenced at the
    // end of the chain back again with a different object reference than the
    // one at the beginning of the chain.
    $entity_level_zero->$field_name->appendItem($entity_level_one);
    // Ensure that the main entity has different object references by cloning
    // it, otherwise PHP will not serialize the same object twice.
    $entity_level_zero_clone = clone $entity_level_zero;
    $this->assertNotSame($entity_level_zero, $entity_level_zero_clone);
    $entity_level_one->$field_name->appendItem($entity_level_zero_clone);

    // Make sure all the entities are initialized in the structure, otherwise
    // they will not be contained in the serialization.
    $entity_level_zero->$field_name->entity->$field_name->entity;

    // Now serialize the entity structure through deep serialization.
    $entity_level_zero->setDeepSerialization(TRUE);
    $serialized_main_entity = serialize($entity_level_zero);
    $this->assertEquals(2, substr_count($serialized_main_entity, 'entity zero'));
    $this->assertEquals(1, substr_count($serialized_main_entity, 'entity one'));

    // Case 3: existing_entity_a->existing_entity_b->existing_entity_a
    // Reset the entities for the next test.
    $entity_level_zero = $this->entityStorage->loadUnchanged($entity_level_zero->id());
    $entity_level_one = $this->entityStorage->loadUnchanged($entity_level_one->id());

    // Create a circular reference, where the parent entity is referenced at the
    // end of the chain back again with the same object reference as at the
    // beginning of the chain.
    $entity_level_zero->$field_name->appendItem($entity_level_one);
    $entity_level_one->$field_name->appendItem($entity_level_zero);

    // Make sure all the entities are initialized in the structure, otherwise
    // they will not be contained in the serialization.
    $entity_level_zero->$field_name->entity->$field_name->entity;

    // Now serialize the entity structure through deep serialization.
    $entity_level_zero->setDeepSerialization(TRUE);
    $serialized_main_entity = serialize($entity_level_zero);
    $this->assertEquals(2, substr_count($serialized_main_entity, 'entity zero'));
    $this->assertEquals(2, substr_count($serialized_main_entity, 'entity one'));

    // Case 4 : Like the previous case, but now show that no matter how deep a
    // nested structure with circular references is initialized the serialized
    // string will remain the same and will contain the serialized values to the
    // same amount as when the structure is not initialized that deep.

    // Reset the entities for the next test.
    $entity_level_zero = $this->entityStorage->loadUnchanged($entity_level_zero->id());
    $entity_level_one = $this->entityStorage->loadUnchanged($entity_level_one->id());

    // Create a circular reference, where the parent entity is referenced at the
    // end of the chain back again with the same object reference as at the
    // beginning of the chain.
    $entity_level_zero->$field_name->appendItem($entity_level_one);
    $entity_level_one->$field_name->appendItem($entity_level_zero);

    // Make sure all the entities are initialized in the structure, otherwise
    // they will not be contained in the serialization.
    $entity_level_zero->$field_name->entity->$field_name->entity;

    // Now serialize the entity structure through deep serialization.
    $entity_level_zero->setDeepSerialization(TRUE);
    $serialized_main_entity_case_4a = serialize($entity_level_zero);
    $this->assertEquals(2, substr_count($serialized_main_entity_case_4a, 'entity zero'));
    $this->assertEquals(2, substr_count($serialized_main_entity_case_4a, 'entity one'));

    // Make sure all the entities are initialized in the structure, otherwise
    // they will not be contained in the serialization.
    $entity_level_zero->$field_name->entity->$field_name->entity->$field_name->entity->$field_name->entity;

    // Now serialize the entity structure through deep serialization.
    $entity_level_zero->setDeepSerialization(TRUE);
    $serialized_main_entity_case_4b = serialize($entity_level_zero);
    $this->assertEquals(2, substr_count($serialized_main_entity_case_4b, 'entity zero'));
    $this->assertEquals(2, substr_count($serialized_main_entity_case_4b, 'entity one'));

    // Make sure all the entities are initialized in the structure, otherwise
    // they will not be contained in the serialization.
    $entity_level_zero->$field_name->entity->$field_name->entity->$field_name->entity->$field_name->entity->$field_name->entity->$field_name->entity;

    // Now serialize the entity structure through deep serialization.
    $entity_level_zero->setDeepSerialization(TRUE);
    $serialized_main_entity_case_4c = serialize($entity_level_zero);
    $this->assertEquals(2, substr_count($serialized_main_entity_case_4c, 'entity zero'));
    $this->assertEquals(2, substr_count($serialized_main_entity_case_4c, 'entity one'));

    // Assert that the serialization result does not depend on how deep the
    // nested structure with circular references is initialized.
    $this->assertEquals($serialized_main_entity_case_4a, $serialized_main_entity_case_4b);
    $this->assertEquals($serialized_main_entity_case_4b, $serialized_main_entity_case_4c);
  }

}
