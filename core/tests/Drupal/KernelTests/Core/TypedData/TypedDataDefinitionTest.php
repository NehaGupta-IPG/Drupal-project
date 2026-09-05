<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\TypedData;

use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinition;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceInterface;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests deriving metadata of core data types.
 */
#[Group('TypedData')]
#[RunTestsInSeparateProcesses]
class TypedDataDefinitionTest extends KernelTestBase {

  /**
   * The typed data manager to use.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
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
   * Tests deriving metadata about list items.
   */
  public function testLists(): void {
    $list_definition = ListDataDefinition::create('string');
    $this->assertInstanceOf(ListDataDefinitionInterface::class, $list_definition);
    $item_definition = $list_definition->getItemDefinition();
    $this->assertInstanceOf(DataDefinitionInterface::class, $item_definition);
    $this->assertEquals('string', $item_definition->getDataType());

    // Test using the definition factory.
    $list_definition2 = $this->typedDataManager->createListDataDefinition('string');
    $this->assertInstanceOf(ListDataDefinitionInterface::class, $list_definition2);
    $this->assertEquals($list_definition, $list_definition2);

    // Test creating the definition of data with type 'list', which is the same
    // as creating a definition of a list of items of type 'any'.
    $list_definition = $this->typedDataManager->createDataDefinition('list');
    $this->assertInstanceOf(ListDataDefinitionInterface::class, $list_definition);
    $this->assertEquals('list', $list_definition->getDataType());
    $this->assertEquals('any', $list_definition->getItemDefinition()->getDataType());
  }

  /**
   * Tests deriving metadata about maps.
   */
  public function testMaps(): void {
    $map_definition = MapDataDefinition::create()
      ->setPropertyDefinition('one', DataDefinition::create('string'))
      ->setPropertyDefinition('two', DataDefinition::create('string'))
      ->setPropertyDefinition('three', DataDefinition::create('string'));

    $this->assertInstanceOf(ComplexDataDefinitionInterface::class, $map_definition);

    // Test retrieving metadata about contained properties.
    $this->assertEquals(['one', 'two', 'three'], array_keys($map_definition->getPropertyDefinitions()));
    $this->assertEquals('string', $map_definition->getPropertyDefinition('one')->getDataType());
    $this->assertNull($map_definition->getMainPropertyName());
    $this->assertNull($map_definition->getPropertyDefinition('invalid'));

    // Test using the definition factory.
    $map_definition2 = $this->typedDataManager->createDataDefinition('map');
    $this->assertInstanceOf(ComplexDataDefinitionInterface::class, $map_definition2);
    $map_definition2->setPropertyDefinition('one', DataDefinition::create('string'))
      ->setPropertyDefinition('two', DataDefinition::create('string'))
      ->setPropertyDefinition('three', DataDefinition::create('string'));
    $this->assertEquals(serialize($map_definition2), serialize($map_definition));
  }

  /**
   * Tests deriving metadata from data references.
   */
  public function testDataReferences(): void {
    $language_reference_definition = DataReferenceDefinition::create('language');
    $this->assertInstanceOf(DataReferenceDefinitionInterface::class, $language_reference_definition);

    // Test retrieving metadata about the referenced data.
    $this->assertEquals('language', $language_reference_definition->getTargetDefinition()->getDataType());

    // Test using the definition factory.
    $language_reference_definition2 = $this->typedDataManager->createDataDefinition('language_reference');
    $this->assertInstanceOf(DataReferenceDefinitionInterface::class, $language_reference_definition2);
    $this->assertEquals(serialize($language_reference_definition2), serialize($language_reference_definition));
  }

  /**
   * Tests that a declared default value is metadata only.
   */
  public function testDefault(): void {
    $definition = DataDefinition::create('string')->setDefault('foo');

    // Creating typed data does not apply the declared default value.
    $data = $this->typedDataManager->create($definition);
    $this->assertNull($data->getValue());
    $this->assertTrue($data->getDataDefinition()->hasDefault());
    $this->assertSame('foo', $data->getDataDefinition()->getDefault());

    // Neither does applying the default value of the typed data object.
    $data->applyDefaultValue();
    $this->assertNull($data->getValue());

    // A value passed to ::create() is used as-is.
    $data = $this->typedDataManager->create($definition, 'bar');
    $this->assertSame('bar', $data->getValue());

    // A declared default value does not influence validation: the violations
    // are the same as for a definition without a default value.
    $definition = DataDefinition::create('string')->setRequired(TRUE);
    $expected = (string) $this->typedDataManager->create($definition)->validate();
    $definition->setDefault('foo');
    $violations = $this->typedDataManager->create($definition)->validate();
    $this->assertCount(1, $violations);
    $this->assertSame($expected, (string) $violations);

    // The default value of list items and map properties is not applied
    // either.
    $list_definition = ListDataDefinition::create('string');
    $list_definition->getItemDefinition()->setDefault('foo');
    $list = $this->typedDataManager->create($list_definition);
    $this->assertSame([], $list->getValue());
    $list->applyDefaultValue();
    $this->assertSame([], $list->getValue());

    $map_definition = MapDataDefinition::create()
      ->setPropertyDefinition('one', DataDefinition::create('string')->setDefault('foo'));
    $map = $this->typedDataManager->create($map_definition);
    $this->assertNull($map->get('one')->getValue());
    $map->applyDefaultValue();
    $this->assertNull($map->get('one')->getValue());
  }

  /**
   * Tests getString() throws exception when getType() is not implemented.
   */
  public function testNotImplementedGetType(): void {
    $language_reference_definition = DataReferenceDefinition::create('language');
    $language_reference = $this->typedDataManager->create($language_reference_definition);
    $this->assertInstanceOf(DataReferenceInterface::class, $language_reference);
    $this->expectException(\BadMethodCallException::class);
    $this->expectExceptionMessageMatches('/getType\(\) not implemented/');
    $language_reference->getString();
  }

}
