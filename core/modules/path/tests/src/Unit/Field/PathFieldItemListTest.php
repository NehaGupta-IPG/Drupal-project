<?php

namespace Drupal\Tests\path\Unit\Field;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\path\Plugin\Field\FieldType\PathFieldItemList;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\path\Plugin\Field\FieldType\PathFieldItemList
 * @group path
 */
class PathFieldItemListTest extends UnitTestCase {

  /**
   * @covers ::getValue
   *
   * @param bool $is_routed
   *   Whether the parent entity has internal path.
   * @param bool $is_new
   *   Whether the parent entity is new.
   * @param array|null $expected_path_alias
   *   Expected array returned by path_alias.repository services.
   *
   * @dataProvider providerTestComputeValue
   */
  public function testComputeValue(bool $is_routed, bool $is_new, $expected_path_alias): void {

    // Our expected value is the expected path alias with minor changes.
    if ($expected_path_alias) {
      $expected = [
        'alias' => $expected_path_alias['alias'],
        'pid' => $expected_path_alias['id'],
        'langcode' => $expected_path_alias['langcode'],
      ];
    }
    else {
      $expected['langcode'] = 'und';
    }

    $created_value = $this->createMock('Drupal\Core\TypedData\TypedDataInterface');
    $created_value->expects($this->any())
      ->method('getValue')
      ->willReturn($expected);

    $url = $this->createMock('Drupal\Core\Url');
    $url->expects($this->any())
      ->method('isRouted')
      ->willReturn($is_routed);
    if ($is_routed) {
      $url->expects($this->any())
        ->method('getInternalPath')
        ->willReturn('some_internal_path');
    }
    else {
      $url->expects($this->any())
        ->method('getInternalPath')
        ->willThrowException(new \UnexpectedValueException());
    }

    $parent_entity = $this->createMock('Drupal\Core\Entity\EntityInterface');
    $parent_entity->expects($this->once())
      ->method('isNew')
      ->willReturn($is_new);
    $parent_entity->expects($this->any())
      ->method('toUrl')
      ->willReturn($url);

    $parent_typed_data = $this->createMock('Drupal\Core\TypedData\TypedDataInterface');
    $parent_typed_data->expects($this->once())
      ->method('getValue')
      ->willReturn($parent_entity);

    $field_definition = $this->createMock('Drupal\Core\Field\FieldDefinitionInterface');

    $path_field_list = new PathFieldItemList($field_definition, NULL, $parent_typed_data);

    // Dependency injection is not used twice within the scope of the covered
    // code. First, \Drupal::service('path_alias.repository') is called
    // in PathFieldItemList::computeValue() (which itself is called from
    // PathFieldItemList::getValue()). Second,
    // \Drupal::service('plugin.manager.field.field_type') is called from
    // PathFieldItemList::createItem() (which itself is called from
    // PathFieldItemList::computeValue()). Therefore, we must mock the field
    // type manager and path alias repository and set them on the container.
    $field_type_manager = $this->createMock('Drupal\Core\Field\FieldTypePluginManagerInterface');
    $field_type_manager->expects($this->any())
      ->method('createFieldItem')
      ->with($path_field_list, 0, $expected)
      ->willReturn($created_value);
    $path_alias_repository = $this->createMock('Drupal\path_alias\AliasRepositoryInterface');
    $path_alias_repository->expects($this->any())
      ->method('lookupBySystemPath')
      ->willReturn($expected_path_alias);
    $container = new ContainerBuilder();
    $container->set('path_alias.repository', $path_alias_repository);
    $container->set('plugin.manager.field.field_type', $field_type_manager);
    \Drupal::setContainer($container);

    $this->assertSame($expected, $path_field_list->getValue()[0]);
  }

  /**
   * Data provider for testComputeValue.
   */
  public function providerTestComputeValue(): array {
    return [
      'new entity' => [
        'is_routed' => FALSE,
        'is_new' => TRUE,
        'expected_path_alias' => NULL,
      ],
      'entity with internal path' => [
        'is_routed' => TRUE,
        'is_new' => FALSE,
        'expected_path_alias' => [
          'alias' => 'some_alias',
          'id' => 123,
          'langcode' => 'und',
        ],
      ],
      'entity without internal path' => [
        'is_routed' => FALSE,
        'is_new' => FALSE,
        'expected_path_alias' => NULL,
      ],
    ];
  }

}
