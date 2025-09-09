<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Field;

use Drupal\Core\Field\FieldItemBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMulRev;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Test field item methods.
 *
 * @group Field
 */
class FieldItemTest extends EntityKernelTestBase {

  /**
   * The name of the test field.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container->get('state')->set('entity_test.field_test_item', TRUE);
    $this->entityTypeManager->clearCachedDefinitions();

    $entity_type_id = 'entity_test_mulrev';
    $this->installEntitySchema($entity_type_id);

    $this->fieldName = $this->randomMachineName();

    FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'type' => 'field_test',
      'entity_type' => $entity_type_id,
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'entity_type' => $entity_type_id,
      'field_name' => $this->fieldName,
      'bundle' => $entity_type_id,
      'label' => 'Test field',
    ])->save();

    $this->entityTypeManager->clearCachedDefinitions();
    $definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);
    $this->assertNotEmpty($definitions[$this->fieldName]);
  }

  /**
   * Tests the field item save workflow.
   */
  public function testSaveWorkflow(): void {
    $entity = EntityTestMulRev::create([
      'name' => $this->randomString(),
      'field_test_item' => $this->randomString(),
      $this->fieldName => $this->randomString(),
    ]);

    $this->assertSavedFieldItemValue($entity, "field_test:{$this->fieldName}:1:1");
    $this->assertSavedFieldItemValue($entity, 'overwritten');

    $entity->field_test_item->value = $entity->{$this->fieldName}->value = 'resave';
    $this->assertSavedFieldItemValue($entity, "field_test:{$this->fieldName}:1:3");
  }

  /**
   * Checks that the saved field item value matches the expected one.
   *
   * @param \Drupal\entity_test\Entity\EntityTest $entity
   *   The test entity.
   * @param string $expected_value
   *   The expected field item value.
   *
   * @internal
   */
  protected function assertSavedFieldItemValue(EntityTest $entity, string $expected_value): void {
    $entity->setNewRevision(TRUE);
    $entity->save();
    $base_field_expected_value = str_replace($this->fieldName, 'field_test_item', $expected_value);
    $this->assertEquals($base_field_expected_value, $entity->field_test_item->value);
    $this->assertEquals($expected_value, $entity->{$this->fieldName}->value);
    $entity = $this->reloadEntity($entity);
    $this->assertEquals($base_field_expected_value, $entity->field_test_item->value);
    $this->assertEquals($expected_value, $entity->{$this->fieldName}->value);
  }

  /**
   * Tests \Drupal\Core\Field\TypedData\FieldItemDataDefinition::getLabel().
   */
  public function testGetLabel(): void {
    $data_definition = \Drupal::service('typed_data_manager')->createDataDefinition('field_item:string');
    $this->assertEquals('Text (plain)', $data_definition->getLabel());

    $label = 'Foo bar';
    $data_definition->setLabel($label);
    $this->assertEquals($label, $data_definition->getLabel());
  }

  /**
   * Tests the new property definition methods.
   */
  public function testPropertyDefinitions(): void {
    $entity = EntityTestMulRev::create([
      'name' => 'test entity',
      $this->fieldName => 'test value',
    ]);
    $field_item = $entity->{$this->fieldName}[0];

    // getPropertyDefinitions() proxies to the data definition.
    $definitions = $field_item->getPropertyDefinitions();
    $this->assertIsArray($definitions);
    $this->assertArrayHasKey('value', $definitions);

    // getPropertyDefinition($name) returns a single definition.
    $value_definition = $field_item->getPropertyDefinition('value');
    $this->assertNotNull($value_definition);

    // Instead of getName(), assert the data type (Drupal 11 compatible).
    $this->assertEquals('string', $value_definition->getDataType());
  }

  /**
   * Tests nullsafe operators in getEntity() and getLangcode.
   *
   * @note cspell:ignore nullsafe Nullsafe
   */
  public function testNullsafeGetEntityAndLangcode(): void {
    $definition = \Drupal::typedDataManager()->createDataDefinition('field_item:string');

    $field_item = new class($definition) extends FieldItemBase {

      /**
       * {@inheritdoc}
       *
       * Defines the properties of the field item.
       *
       * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_definition
       *   The field storage definition for which to define properties.
       *
       * @return array
       *   An array of property definitions for this field item.
       */
      public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
        return [];
      }

      /**
       * {@inheritdoc}
       *
       * Provides the database schema for the field storage of this field item.
       *
       * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_definition
       *   The field storage definition for which to generate the schema.
       *
       * @return array
       *   An array representing the database schema for this field item.
       */
      public static function schema(FieldStorageDefinitionInterface $field_definition): array {
        return [];
      }

    };

    $this->assertNull($field_item->getEntity());
    $this->assertNull($field_item->getLangcode());
  }

}
