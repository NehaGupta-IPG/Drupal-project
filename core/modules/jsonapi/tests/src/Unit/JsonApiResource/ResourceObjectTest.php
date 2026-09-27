<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi\Unit\JsonApiResource;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeAttribute;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\jsonapi\JsonApiResource\ResourceObject.
 *
 * @internal
 */
#[CoversClass(ResourceObject::class)]
#[Group('jsonapi')]
class ResourceObjectTest extends UnitTestCase {

  /**
   * Tests that only enabled, non-internal fields are instantiated.
   *
   * @legacy-covers ::extractContentEntityFields
   */
  public function testExtractContentEntityFieldsSkipsUnusedFields(): void {
    $resource_type = new ResourceType('entity_test', 'entity_test', ContentEntityInterface::class, fields: [
      'enabled' => new ResourceTypeAttribute('enabled'),
      'disabled' => new ResourceTypeAttribute('disabled', NULL, FALSE),
      'internal' => new ResourceTypeAttribute('internal'),
      'aliased' => new ResourceTypeAttribute('aliased', 'alias'),
    ]);

    $definitions = [];
    foreach (['enabled' => FALSE, 'disabled' => FALSE, 'internal' => TRUE, 'unknown' => FALSE, 'aliased' => FALSE] as $field_name => $internal) {
      $definition = $this->createStub(FieldDefinitionInterface::class);
      $definition->method('isInternal')->willReturn($internal);
      $definitions[$field_name] = $definition;
    }
    $enabled_field = $this->createStub(FieldItemListInterface::class);
    $aliased_field = $this->createStub(FieldItemListInterface::class);

    $entity_type = $this->createStub(EntityTypeInterface::class);
    $entity_type->method('id')->willReturn('entity_test');
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->method('getFieldDefinitions')->willReturn($definitions);
    // Disabled, internal, and unknown fields must never be instantiated.
    $entity->expects($this->exactly(2))
      ->method('get')
      ->willReturnCallback(fn (string $field_name) => match ($field_name) {
        'enabled' => $enabled_field,
        'aliased' => $aliased_field,
      });

    $fields = (new \ReflectionMethod(ResourceObject::class, 'extractContentEntityFields'))
      ->invoke(NULL, $resource_type, $entity);

    $this->assertSame(['enabled' => $enabled_field, 'alias' => $aliased_field], $fields);
  }

}
