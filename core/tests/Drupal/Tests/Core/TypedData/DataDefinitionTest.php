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
   * Tests that no examples are declared unless they are set.
   */
  public function testNoExamples(): void {
    $definition = DataDefinition::create('string');
    $this->assertSame([], $definition->getExamples());
    $this->assertArrayNotHasKey('examples', $definition->toArray());
  }

  /**
   * Data provider for ::testSetExamples().
   */
  public static function providerSetExamples(): array {
    return [
      'strings' => [['foo', 'bar']],
      'integers' => [[1, 2, 3]],
      'mixed' => [['foo', 42, 1.5, TRUE, NULL]],
      'single example' => [['foo']],
      'array values' => [[['one' => 1], ['one' => 2]]],
      'empty' => [[]],
    ];
  }

  /**
   * Tests declaring example values.
   */
  #[DataProvider('providerSetExamples')]
  public function testSetExamples(array $examples): void {
    $definition = DataDefinition::create('any');
    $this->assertSame($definition, $definition->setExamples($examples));
    $this->assertSame($examples, $definition->getExamples());
    $this->assertArrayHasKey('examples', $definition->toArray());
    $this->assertSame($examples, $definition->toArray()['examples']);
  }

  /**
   * Tests that an empty array removes previously declared examples.
   */
  public function testRemoveExamples(): void {
    $definition = DataDefinition::create('string')->setExamples(['foo']);
    $this->assertSame(['foo'], $definition->getExamples());
    $definition->setExamples([]);
    $this->assertSame([], $definition->getExamples());
  }

  /**
   * Tests that examples can be passed to the constructor.
   */
  public function testExamplesFromConstructor(): void {
    $definition = new DataDefinition(['type' => 'string', 'examples' => ['foo']]);
    $this->assertSame(['foo'], $definition->getExamples());
  }

  /**
   * Tests that examples are available on definition subclasses.
   */
  public function testExamplesOnSubclasses(): void {
    // Use the constructor directly, as ::create() needs the typed data manager.
    $list_definition = new ListDataDefinition([], DataDefinition::create('string'));
    $list_definition->setExamples([['a', 'b'], ['c']]);
    $this->assertSame([['a', 'b'], ['c']], $list_definition->getExamples());
    // The examples of a list are not propagated to its item definition.
    $this->assertSame([], $list_definition->getItemDefinition()->getExamples());

    $map_definition = MapDataDefinition::create()
      ->setPropertyDefinition('one', DataDefinition::create('string')->setExamples(['foo']))
      ->setPropertyDefinition('two', DataDefinition::create('string'));
    $this->assertSame([], $map_definition->getExamples());
    $this->assertSame(['foo'], $map_definition->getPropertyDefinition('one')->getExamples());
    $this->assertSame([], $map_definition->getPropertyDefinition('two')->getExamples());
  }

  /**
   * Tests that examples survive serialization.
   */
  public function testExamplesAreSerialized(): void {
    $definition = DataDefinition::create('string')->setExamples(['foo', 'bar']);
    $unserialized = unserialize(serialize($definition));
    $this->assertSame(['foo', 'bar'], $unserialized->getExamples());
  }

}
