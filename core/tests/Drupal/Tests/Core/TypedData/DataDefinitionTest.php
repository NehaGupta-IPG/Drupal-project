<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\TypedData;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the DataDefinition class.
 */
#[CoversClass(DataDefinition::class)]
#[Group('TypedData')]
class DataDefinitionTest extends UnitTestCase {

  /**
   * Tests that no default value is declared unless one is set.
   */
  public function testNoDefault(): void {
    $definition = DataDefinition::create('string');
    $this->assertNull($definition->getDefault());
    $this->assertArrayNotHasKey('default', $definition->toArray());
  }

  /**
   * Data provider for ::testSetDefault().
   */
  public static function providerSetDefault(): array {
    return [
      'string' => ['foo'],
      'integer' => [42],
      'float' => [1.5],
      'boolean TRUE' => [TRUE],
      'boolean FALSE' => [FALSE],
      'empty string' => [''],
      'zero' => [0],
      'empty array' => [[]],
      'array' => [['one' => 1, 'two' => 2]],
      'NULL' => [NULL],
    ];
  }

  /**
   * Tests declaring a default value.
   */
  #[DataProvider('providerSetDefault')]
  public function testSetDefault(mixed $value): void {
    $definition = DataDefinition::create('any');
    $this->assertSame($definition, $definition->setDefault($value));
    $this->assertSame($value, $definition->getDefault());
    $this->assertArrayHasKey('default', $definition->toArray());
    $this->assertSame($value, $definition->toArray()['default']);
  }

  /**
   * Tests that ::getDefault() reads a NULL default value as no default value.
   */
  public function testNullDefault(): void {
    $definition = DataDefinition::create('string');
    $definition->setDefault(NULL);
    $this->assertNull($definition->getDefault());
    // The declaration is still recorded in the definition array.
    $this->assertArrayHasKey('default', $definition->toArray());

    // Setting NULL after another value does not remove the declaration.
    $definition->setDefault('foo')->setDefault(NULL);
    $this->assertNull($definition->getDefault());
    $this->assertArrayHasKey('default', $definition->toArray());
  }

  /**
   * Tests that a default value can be passed to the constructor.
   */
  public function testDefaultFromConstructor(): void {
    $definition = new DataDefinition(['type' => 'string', 'default' => 'foo']);
    $this->assertSame('foo', $definition->getDefault());

    $definition = new DataDefinition(['type' => 'string', 'default' => NULL]);
    $this->assertNull($definition->getDefault());
    $this->assertArrayHasKey('default', $definition->toArray());
  }

  /**
   * Tests that the default value can be removed via array access.
   */
  public function testRemoveDefaultViaArrayAccess(): void {
    $definition = DataDefinition::create('string')->setDefault('foo');
    $this->assertSame('foo', $definition->getDefault());
    unset($definition['default']);
    $this->assertNull($definition->getDefault());
    $this->assertArrayNotHasKey('default', $definition->toArray());
  }

  /**
   * Tests that the default value is available on definition subclasses.
   */
  public function testDefaultOnSubclasses(): void {
    // Use the constructor directly, as ::create() needs the typed data manager.
    $list_definition = new ListDataDefinition([], DataDefinition::create('string'));
    $list_definition->setDefault(['a', 'b']);
    $this->assertSame(['a', 'b'], $list_definition->getDefault());
    // The default value of a list is not propagated to its item definition.
    $this->assertNull($list_definition->getItemDefinition()->getDefault());

    $map_definition = MapDataDefinition::create()
      ->setPropertyDefinition('one', DataDefinition::create('string')->setDefault('foo'))
      ->setPropertyDefinition('two', DataDefinition::create('string'));
    $this->assertNull($map_definition->getDefault());
    $this->assertSame('foo', $map_definition->getPropertyDefinition('one')->getDefault());
    $this->assertNull($map_definition->getPropertyDefinition('two')->getDefault());
  }

  /**
   * Tests that the default value survives serialization.
   */
  public function testDefaultIsSerialized(): void {
    $definition = DataDefinition::create('string')->setDefault('foo');
    $unserialized = unserialize(serialize($definition));
    $this->assertSame('foo', $unserialized->getDefault());
  }

}
