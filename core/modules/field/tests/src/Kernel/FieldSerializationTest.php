<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\Core\Field\TypedData\FieldItemDataDefinition;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests field definition serialization behavior.
 */
#[CoversClass(BaseFieldDefinition::class)]
#[CoversClass(BaseFieldOverride::class)]
#[CoversClass(FieldConfig::class)]
#[CoversClass(FieldStorageConfig::class)]
#[Group('field')]
#[RunTestsInSeparateProcesses]
class FieldSerializationTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('base_field_override');
  }

  /**
   * Tests that field config entities drop their derived item definitions.
   */
  public function testFieldConfigSerializationDropsDerivedState(): void {
    $this->createFieldWithStorage();

    $field = FieldConfig::load($this->fieldTestData->field->id());
    $item_definition = $field->getItemDefinition();

    /** @var \Drupal\field\Entity\FieldConfig $unserialized */
    $unserialized = unserialize(serialize($field));

    $this->assertNull($this->getPropertyValue($unserialized, 'itemDefinition'));

    $new_item_definition = $unserialized->getItemDefinition();
    $this->assertInstanceOf(FieldItemDataDefinition::class, $new_item_definition);
    $this->assertNotSame($item_definition, $new_item_definition);
    $this->assertSame($item_definition->getDataType(), $new_item_definition->getDataType());
  }

  /**
   * Tests that field storage config entities recompute derived definitions.
   */
  public function testFieldStorageConfigSerializationDropsDerivedState(): void {
    $this->createFieldWithStorage();

    $field_storage = FieldStorageConfig::load($this->fieldTestData->field_storage->id());
    $schema = $field_storage->getSchema();
    $property_definitions = $field_storage->getPropertyDefinitions();

    /** @var \Drupal\field\Entity\FieldStorageConfig $unserialized */
    $unserialized = unserialize(serialize($field_storage));

    $this->assertNull($this->getPropertyValue($unserialized, 'schema'));
    $this->assertNull($this->getPropertyValue($unserialized, 'propertyDefinitions'));
    $this->assertSame($schema, $unserialized->getSchema());
    $this->assertSame(serialize($property_definitions), serialize($unserialized->getPropertyDefinitions()));
  }

  /**
   * Tests that base field overrides drop and lazily restore base definitions.
   */
  public function testBaseFieldOverrideSerializationDropsDerivedState(): void {
    $base_field_definition = \Drupal::service('entity_field.manager')->getBaseFieldDefinitions('entity_test')['name'];
    $this->assertInstanceOf(BaseFieldDefinition::class, $base_field_definition);
    $override = BaseFieldOverride::createFromBaseFieldDefinition($base_field_definition, 'entity_test');

    /** @var \Drupal\Core\Field\Entity\BaseFieldOverride $unserialized */
    $unserialized = unserialize(serialize($override));

    $this->assertNull($this->getPropertyValue($unserialized, 'baseFieldDefinition'));
    $this->assertSame(ltrim($base_field_definition->getClass(), '\\'), ltrim($unserialized->getClass(), '\\'));
    $this->assertSame($base_field_definition->getUniqueIdentifier(), $unserialized->getUniqueIdentifier());
  }

  /**
   * Tests that base field definitions drop their runtime caches.
   */
  public function testBaseFieldDefinitionSerializationDropsDerivedState(): void {
    $definition = BaseFieldDefinition::create('string')
      ->setName('name')
      ->setTargetEntityTypeId('entity_test');
    $property_definitions = $definition->getPropertyDefinitions();
    $definition->getTypedDataManager();

    /** @var \Drupal\Core\Field\BaseFieldDefinition $unserialized */
    $unserialized = unserialize(serialize($definition));

    $this->assertNull($this->getPropertyValue($unserialized, 'propertyDefinitions'));
    $this->assertNull($this->getPropertyValue($unserialized, 'typedDataManager'));
    $this->assertSame($definition->getType(), $unserialized->getType());
    $this->assertSame(serialize($property_definitions), serialize($unserialized->getPropertyDefinitions()));
  }

  /**
   * Reads a protected property value.
   */
  private function getPropertyValue(object $object, string $property_name): mixed {
    $property = new \ReflectionProperty($object, $property_name);
    return $property->getValue($object);
  }

}
