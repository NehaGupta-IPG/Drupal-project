<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityWeightTrait;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the entity weight trait.
 */
#[CoversClass(EntityWeightTrait::class)]
#[Group('Entity')]
class EntityWeightTraitTest extends UnitTestCase {

  /**
   * Tests the getWeight() and setWeight() methods.
   */
  public function testWeightMethods(): void {
    // 1. Mock the EntityType definition to handle dynamic keys.
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getKey')
      ->with('weight')
      ->willReturn('weight');

    // 2. Natively consume the Trait inside a pure PHP 8 Anonymous Class.
    $entity = new class($entity_type) {
      use EntityWeightTrait;

      /**
       * The mock weight value.
       *
       * @var int|null
       */
      public ?int $mockWeight = NULL;

      /**
       * The mock entity type.
       *
       * @var \Drupal\Core\Entity\EntityTypeInterface
       */
      protected EntityTypeInterface $entityType;

      /**
       * Constructs a new anonymous entity object.
       *
       * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
       *   The entity type interface.
       */
      public function __construct(EntityTypeInterface $entityType) {
        $this->entityType = $entityType;
      }

      /**
       * Fulfills the internal getEntityKey method expectation.
       *
       * @param string $key
       *   The key to get.
       *
       * @return int|null
       *   The mocked weight, or NULL.
       */
      public function getEntityKey(string $key): ?int {
        return $key === 'weight' ? $this->mockWeight : NULL;
      }

      /**
       * Fulfills the internal getEntityType method expectation.
       *
       * @return \Drupal\Core\Entity\EntityTypeInterface
       *   The mock entity type.
       */
      public function getEntityType(): EntityTypeInterface {
        return $this->entityType;
      }

      /**
       * Fulfills the internal set method expectation.
       *
       * @param string $key
       *   The key to set.
       * @param mixed $value
       *   The value to set.
       *
       * @return static
       *   The current entity instance.
       */
      public function set(string $key, $value): static {
        if ($key === 'weight') {
          $this->mockWeight = $value;
        }
        return $this;
      }

    };

    // --- Assertions ---

    // Assert default state is 0.
    $this->assertSame(0, $entity->getWeight());

    // Assert setting a positive weight returns the entity instance.
    $this->assertSame($entity, $entity->setWeight(5));
    // Assert the getter returns that positive weight.
    $this->assertSame(5, $entity->getWeight());

    // Assert setting a negative weight works perfectly.
    $this->assertSame($entity, $entity->setWeight(-10));
    $this->assertSame(-10, $entity->getWeight());
  }

}
