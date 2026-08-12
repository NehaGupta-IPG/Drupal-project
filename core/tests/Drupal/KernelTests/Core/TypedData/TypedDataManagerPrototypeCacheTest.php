<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\TypedData;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\Core\TypedData\Plugin\DataType\IntegerData;
use Drupal\Core\TypedData\Plugin\DataType\Map;
use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcesses;

/**
 * Tests TypedDataManager prototype cache does not collide across definitions.
 *
 * TypedDataManager::getPropertyInstance() caches prototype objects keyed by
 * "{root_data_type}:{settings}:{property_path}:{property_name}". This key does
 * not include the property's own data type, class, or constraints, causing
 * cache collisions when two different MapDataDefinition or ListDataDefinition
 * instances define a property/item with the same name but different types or
 * constraints.
 *
 * @see \Drupal\Core\TypedData\TypedDataManager::getPropertyInstance()
 */
#[Group('TypedData')]
#[RunInSeparateProcesses]
class TypedDataManagerPrototypeCacheTest extends KernelTestBase {

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->typedDataManager = $this->container->get('typed_data_manager');
  }

  /**
   * Tests Map cached as prototype does not collide with a scalar property.
   *
   * Variant 1: When the first Map defines a property as a nested Map and the
   * second Map defines the same property name as a string, accessing the
   * property on the second Map should return StringData, not a Map prototype.
   */
  public function testMapPropertyTypeCollisionThrowsException(): void {
    // Map A: 'title' is a nested Map.
    $map_a = MapDataDefinition::create('map');
    $map_a->setPropertyDefinition('title', MapDataDefinition::create('map'));
    $instance_a = $this->typedDataManager->create($map_a, ['title' => []]);
    // Access the property to populate the prototype cache.
    $prop_a = $instance_a->get('title');
    $this->assertInstanceOf(Map::class, $prop_a);

    // Map B: 'title' is a plain string.
    $map_b = MapDataDefinition::create('map');
    $map_b->setPropertyDefinition('title', DataDefinition::create('string'));
    $instance_b = $this->typedDataManager->create($map_b, ['title' => 'Hello']);

    // Accessing 'title' on Map B must return StringData, not the cached Map.
    $prop_b = $instance_b->get('title');
    $this->assertInstanceOf(StringData::class, $prop_b, 'Property "title" on Map B should be StringData, not a cached Map prototype.');
    $this->assertSame('Hello', $prop_b->getValue());
  }

  /**
   * Tests different scalar types do not collide in the prototype cache.
   *
   * Variant 2: When the first Map defines a property as a string and the
   * second defines the same property name as an integer, the second should
   * return IntegerData, not StringData.
   */
  public function testMapPropertyScalarTypeCollision(): void {
    // Map A: 'count' is a string.
    $map_a = MapDataDefinition::create('map');
    $map_a->setPropertyDefinition('count', DataDefinition::create('string'));
    $instance_a = $this->typedDataManager->create($map_a, ['count' => 'five']);
    // Populate the prototype cache with StringData.
    $prop_a = $instance_a->get('count');
    $this->assertInstanceOf(StringData::class, $prop_a);

    // Map B: 'count' is an integer.
    $map_b = MapDataDefinition::create('map');
    $map_b->setPropertyDefinition('count', DataDefinition::create('integer'));
    $instance_b = $this->typedDataManager->create($map_b, ['count' => 42]);

    // Accessing 'count' on Map B must return IntegerData, not StringData.
    $prop_b = $instance_b->get('count');
    $this->assertInstanceOf(IntegerData::class, $prop_b, 'Property "count" on Map B should be IntegerData, not the cached StringData prototype.');
    $this->assertSame(42, $prop_b->getValue());
  }

  /**
   * Tests constraints are not silently dropped due to prototype caching.
   *
   * Variant 3: When the first Map defines a property with no constraints and
   * the second defines the same property name with a constraint, validation on
   * the second must still enforce the constraint.
   */
  public function testMapPropertyConstraintsDropped(): void {
    // Map A: 'email' is a string with NO constraints.
    $map_a = MapDataDefinition::create('map');
    $map_a->setPropertyDefinition('email', DataDefinition::create('string'));
    $instance_a = $this->typedDataManager->create($map_a, ['email' => 'not-an-email']);
    // Populate the prototype cache without any constraints.
    $instance_a->get('email');

    // Map B: 'email' is a string WITH an Email constraint.
    $map_b = MapDataDefinition::create('map');
    $email_def = DataDefinition::create('string');
    $email_def->addConstraint('Email');
    $map_b->setPropertyDefinition('email', $email_def);
    $instance_b = $this->typedDataManager->create($map_b, ['email' => 'not-an-email']);

    // Validation must enforce the Email constraint, not use the cached
    // prototype that has no constraints.
    $prop_b = $instance_b->get('email');
    $violations = $prop_b->validate();
    $this->assertGreaterThan(0, $violations->count(), 'The Email constraint should produce violations for an invalid email address, but the cached prototype lost the constraint.');
  }

  /**
   * Tests constraints do not leak from one Map to another via the cache.
   *
   * Variant 4: When the first Map defines a property with constraints and the
   * second defines the same property name without constraints, validation on
   * the second must not enforce the first Map's constraints.
   */
  public function testMapPropertyConstraintsLeaked(): void {
    // Map C: 'name' has a Length(max: 5) constraint.
    $map_c = MapDataDefinition::create('map');
    $name_def = DataDefinition::create('string');
    $name_def->addConstraint('Length', ['max' => 5]);
    $map_c->setPropertyDefinition('name', $name_def);
    $instance_c = $this->typedDataManager->create($map_c, ['name' => 'Hi']);
    // Populate the prototype cache WITH the Length constraint.
    $instance_c->get('name');

    // Map D: 'name' has NO constraints.
    $map_d = MapDataDefinition::create('map');
    $map_d->setPropertyDefinition('name', DataDefinition::create('string'));
    $instance_d = $this->typedDataManager->create($map_d, ['name' => 'A perfectly valid long name']);

    // Validation must NOT enforce the Length constraint from Map C's cached
    // prototype.
    $prop_d = $instance_d->get('name');
    $violations = $prop_d->validate();
    $this->assertCount(0, $violations, 'The Length constraint from Map C should not leak to Map D, but the cached prototype incorrectly enforces it.');
  }

  /**
   * Tests List item constraints are not dropped/leaked via prototype caching.
   *
   * Variant 5: Two ListDataDefinition instances with the same item type but
   * different item constraints should not share a prototype — the first list's
   * constraint state must not win for the second list.
   */
  public function testListItemConstraintsDropped(): void {
    // List A: unconstrained string items.
    $list_a = ListDataDefinition::create('string');
    $la = $this->typedDataManager->create($list_a, ['anything']);
    // Iterate to populate the prototype cache without constraints.
    foreach ($la as $item) {
      break;
    }

    // List B: string items with Length(max: 3) constraint.
    $item_def = DataDefinition::create('string');
    $item_def->addConstraint('Length', ['max' => 3]);
    $list_b = new ListDataDefinition([], $item_def);
    $lb = $this->typedDataManager->create($list_b, ['this is way too long']);

    // Validation must enforce the Length constraint on List B's items.
    foreach ($lb as $item) {
      $violations = $item->validate();
      $this->assertGreaterThan(0, $violations->count(), 'The Length constraint on List B items should produce violations, but the cached prototype from List A lost the constraint.');
    }
  }

}
