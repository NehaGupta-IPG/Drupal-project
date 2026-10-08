<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Field;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\DynamicallyFieldableEntityStorageInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldDefinitionListener;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * Tests Drupal\Core\Field\FieldDefinitionListener.
 */
#[CoversClass(FieldDefinitionListener::class)]
#[Group('Field')]
class FieldDefinitionListenerTest extends UnitTestCase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $entityFieldManager;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $cacheBackend;

  /**
   * The field definition listener under test.
   *
   * @var \Drupal\Core\Field\FieldDefinitionListener
   */
  protected $fieldDefinitionListener;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->entityFieldManager = $this->prophesize(EntityFieldManagerInterface::class);
    $this->cacheBackend = $this->prophesize(CacheBackendInterface::class);

    $this->fieldDefinitionListener = new FieldDefinitionListener($this->entityTypeManager->reveal(), $this->cacheBackend->reveal());
  }

  /**
   * Tests supported constructor signatures and their dependencies.
   */
  #[DataProvider('constructorProvider')]
  #[IgnoreDeprecations]
  public function testConstructor(int $argument_count): void {
    $manager = $this->entityTypeManager->reveal();
    $field_manager = $this->entityFieldManager->reveal();
    $cache = $this->cacheBackend->reveal();
    $key_value = $this->createStub(KeyValueFactoryInterface::class);
    $arguments = [$manager, $cache];
    if ($argument_count > 2) {
      $this->expectUserDeprecationMessage('Calling Drupal\\Core\\Field\\FieldDefinitionListener::__construct() with the $entity_field_manager argument is deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. See https://www.drupal.org/node/3612113');
      $arguments = [$manager, $field_manager, $cache];
    }
    if ($argument_count === 4) {
      $this->expectUserDeprecationMessage('Calling Drupal\\Core\\Field\\FieldDefinitionListener::__construct() with the $key_value_factory argument is deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. See https://www.drupal.org/node/3612113');
      $arguments = [$manager, $field_manager, $key_value, $cache];
    }
    $listener = new FieldDefinitionListener(...$arguments);
    $this->assertSame($manager, (new \ReflectionProperty($listener, 'entityTypeManager'))->getValue($listener));
    $this->assertSame($cache, (new \ReflectionProperty($listener, 'cacheBackend'))->getValue($listener));
    if ($argument_count > 2) {
      $this->assertSame($field_manager, (new \ReflectionProperty($listener, 'entityFieldManager'))->getValue($listener));
    }
    if ($argument_count === 4) {
      $this->assertSame($key_value, (new \ReflectionProperty($listener, 'keyValueFactory'))->getValue($listener));
    }

    $definition = $this->prophesize(FieldDefinitionInterface::class);
    $definition->getTargetEntityTypeId()->willReturn('test_entity_type');
    $storage = $this->prophesize(DynamicallyFieldableEntityStorageInterface::class);
    $storage->onFieldDefinitionCreate($definition->reveal())->shouldBeCalledOnce();
    $storage->onFieldDefinitionUpdate($definition->reveal(), $definition->reveal())->shouldBeCalledOnce();
    $storage->onFieldDefinitionDelete($definition->reveal())->shouldBeCalledOnce();
    $this->entityTypeManager->getStorage('test_entity_type')->willReturn($storage->reveal());
    $this->cacheBackend->delete('entity_field_map')->shouldBeCalledTimes(2);
    $listener->onFieldDefinitionCreate($definition->reveal());
    $listener->onFieldDefinitionUpdate($definition->reveal(), $definition->reveal());
    $listener->onFieldDefinitionDelete($definition->reveal());
  }

  /**
   * Provides supported constructor argument counts.
   */
  public static function constructorProvider(): array {
    return ['current' => [2], 'intermediate' => [3], 'original' => [4]];
  }

  /**
   * Tests notifying storage and clearing the field map on field creation.
   */
  public function testOnFieldDefinitionCreate(): void {
    $field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $field_definition->getTargetEntityTypeId()->willReturn('test_entity_type');

    $storage = $this->prophesize(DynamicallyFieldableEntityStorageInterface::class);
    $storage->onFieldDefinitionCreate($field_definition->reveal())->shouldBeCalledOnce();
    $this->entityTypeManager->getStorage('test_entity_type')->willReturn($storage->reveal());
    $this->cacheBackend->delete('entity_field_map')->shouldBeCalledOnce();

    $this->fieldDefinitionListener->onFieldDefinitionCreate($field_definition->reveal());
  }

  /**
   * Tests notifying storage on field updates.
   */
  public function testOnFieldDefinitionUpdate(): void {
    $field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $field_definition->getTargetEntityTypeId()->willReturn('test_entity_type');
    $original = $this->prophesize(FieldDefinitionInterface::class);

    $storage = $this->prophesize(DynamicallyFieldableEntityStorageInterface::class);
    $storage->onFieldDefinitionUpdate($field_definition->reveal(), $original->reveal())->shouldBeCalledOnce();
    $this->entityTypeManager->getStorage('test_entity_type')->willReturn($storage->reveal());

    $this->fieldDefinitionListener->onFieldDefinitionUpdate($field_definition->reveal(), $original->reveal());
  }

  /**
   * Tests notifying storage and clearing the field map on field deletion.
   */
  public function testOnFieldDefinitionDelete(): void {
    $field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $field_definition->getTargetEntityTypeId()->willReturn('test_entity_type');

    $storage = $this->prophesize(DynamicallyFieldableEntityStorageInterface::class);
    $storage->onFieldDefinitionDelete($field_definition->reveal())->shouldBeCalledOnce();
    $this->entityTypeManager->getStorage('test_entity_type')->willReturn($storage->reveal());
    $this->cacheBackend->delete('entity_field_map')->shouldBeCalledOnce();

    $this->fieldDefinitionListener->onFieldDefinitionDelete($field_definition->reveal());
  }

}
