<?php

declare(strict_types=1);

namespace Drupal\Tests\editor\Unit;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\editor\EntityReferenceHelper;
use PHPUnit\Framework\TestCase;

/**
 * @group editor
 *
 * Test for the EntityReferenceHelper class.
 */
class EntityReferenceHelperTest extends TestCase {

  /**
   * The entity reference helper service.
   *
   * @var \Drupal\editor\EntityReferenceHelper
   */
  protected $helper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->helper = new EntityReferenceHelper();
  }

  /**
   * Test that an empty array is returned when the entity has no fields.
   */
  public function testNoEntityReferenceRevisions(): void {
    // Create a mock entity without any fields.
    $entity = $this->createMock(FieldableEntityInterface::class);
    $entity->method('getFieldDefinitions')
      ->willReturn([]);

    $result = $this->helper->getEntityReferenceRevisions($entity);
    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /**
   * Test that a single referenced entity is returned.
   */
  public function testSingleEntityReferenceRevision(): void {
    $field_name = 'my_field';

    // Simulate the field definition.
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition->method('getType')
      ->willReturn('entity_reference_revisions');

    // Create the main entity.
    $entity = $this->createMock(FieldableEntityInterface::class);
    $entity->method('getFieldDefinitions')
      ->willReturn([$field_name => $field_definition]);

    // Create the referenced entity.
    $referenced_entity = $this->createMock(EntityInterface::class);

    // Create an object that simulates the field item and has the "entity" property.
    $field_item = new \stdClass();
    $field_item->entity = $referenced_entity;

    // Configure the entity so that when requesting the 'my_field' field,
    // it returns the simulated field item.
    $entity->method('get')
      ->with($field_name)
      ->willReturn($field_item);

    $result = $this->helper->getEntityReferenceRevisions($entity);
    $this->assertIsArray($result);
    $this->assertCount(1, $result);
    $this->assertSame($referenced_entity, $result[0]);
  }

  /**
   * Test simple recursion.
   *
   * A main entity references a secondary entity.
   */
  public function testRecursiveEntityReferenceRevision(): void {
    $field_name = 'my_field';

    // Field definition for the main entity.
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition->method('getType')
      ->willReturn('entity_reference_revisions');

    // Main entity with a field 'my_field'.
    $primary_entity = $this->createMock(FieldableEntityInterface::class);
    $primary_entity->method('getFieldDefinitions')
      ->willReturn([$field_name => $field_definition]);

    // Secondary entity (referenced) without additional fields.
    $secondary_entity = $this->createMock(FieldableEntityInterface::class);
    $secondary_entity->method('getFieldDefinitions')
      ->willReturn([]);

    // Configure the main entity's field to return the secondary entity.
    $field_item_primary = new \stdClass();
    $field_item_primary->entity = $secondary_entity;
    $primary_entity->method('get')
      ->with($field_name)
      ->willReturn($field_item_primary);

    $result = $this->helper->getEntityReferenceRevisions($primary_entity);
    $this->assertIsArray($result);
    // Expect only the secondary entity to be present since it has no fields.
    $this->assertCount(1, $result);
    $this->assertSame($secondary_entity, $result[0]);
  }

  /**
   * Test deep recursion.
   *
   * A main entity references a secondary entity, which in turn
   * references a tertiary entity.
   */
  public function testDeepRecursiveEntityReferenceRevision(): void {
    $field_name = 'my_field';

    // Field definition for both entities.
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition->method('getType')
      ->willReturn('entity_reference_revisions');

    // Main entity.
    $primary_entity = $this->createMock(FieldableEntityInterface::class);
    $primary_entity->method('getFieldDefinitions')
      ->willReturn([$field_name => $field_definition]);

    // Secondary entity which is fieldable and has the field 'my_field'.
    $secondary_entity = $this->createMock(FieldableEntityInterface::class);
    $secondary_entity->method('getFieldDefinitions')
      ->willReturn([$field_name => $field_definition]);

    // Tertiary entity without additional fields.
    $tertiary_entity = $this->createMock(FieldableEntityInterface::class);
    $tertiary_entity->method('getFieldDefinitions')
      ->willReturn([]);

    // Configure the main entity to return the secondary entity.
    $field_item_primary = new \stdClass();
    $field_item_primary->entity = $secondary_entity;
    $primary_entity->method('get')
      ->with($field_name)
      ->willReturn($field_item_primary);

    // Configure the secondary entity to return the tertiary entity.
    $field_item_secondary = new \stdClass();
    $field_item_secondary->entity = $tertiary_entity;
    $secondary_entity->method('get')
      ->with($field_name)
      ->willReturn($field_item_secondary);

    $result = $this->helper->getEntityReferenceRevisions($primary_entity);
    $this->assertIsArray($result);
    $this->assertCount(2, $result);
    $this->assertSame($secondary_entity, $result[0]);
    $this->assertSame($tertiary_entity, $result[1]);
  }

}
