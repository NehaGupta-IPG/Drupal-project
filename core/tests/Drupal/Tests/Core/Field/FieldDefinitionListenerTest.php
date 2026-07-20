<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Field;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\DynamicallyFieldableEntityStorageInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldDefinitionListener;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;

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

    $this->fieldDefinitionListener = new FieldDefinitionListener($this->entityTypeManager->reveal(), $this->entityFieldManager->reveal(), $this->cacheBackend->reveal());
  }

  /**
   * Sets up the entity type manager to be tested.
   *
   * @param array<\Prophecy\Prophecy\ProphecyInterface<\Drupal\Core\Entity\EntityTypeInterface>> $definitions
   *   (optional) An array of entity type definitions.
   */
  protected function setUpEntityTypeManager(array $definitions = []): void {
    foreach ($definitions as $key => $entity_type) {
      // \Drupal\Core\Entity\EntityTypeInterface::getLinkTemplates() is called
      // by \Drupal\Core\Entity\EntityTypeManager::processDefinition() so it
      // must always be mocked.
      $entity_type->getLinkTemplates()->willReturn([]);

      $definitions[$key] = $entity_type->reveal();
    }

    $this->entityTypeManager->getDefinition(Argument::cetera())
      ->will(function (array $args) use ($definitions): EntityTypeInterface {
        $entity_type_id = $args[0];
        $exception_on_invalid = $args[1];
        if (isset($definitions[$entity_type_id])) {
          return $definitions[$entity_type_id];
        }
        elseif (!$exception_on_invalid) {
          return NULL;
        }
        else {
          throw new PluginNotFoundException($entity_type_id);
        }
      });
    $this->entityTypeManager->getDefinitions()->willReturn($definitions);
  }

  /**
   * Tests on field definition create new field.
   */
  public function testOnFieldDefinitionCreateNewField(): void {
    $field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $field_definition->getTargetEntityTypeId()->willReturn('test_entity_type');
    $field_definition->getTargetBundle()->willReturn('test_bundle');
    $field_definition->getName()->willReturn('test_field');
    $field_definition->getType()->willReturn('test_type');

    $storage = $this->prophesize(DynamicallyFieldableEntityStorageInterface::class);
    $storage->onFieldDefinitionCreate($field_definition->reveal())->shouldBeCalledTimes(1);
    $this->entityTypeManager->getStorage('test_entity_type')->willReturn($storage->reveal());

    $entity = $this->prophesize(EntityTypeInterface::class);
    $this->setUpEntityTypeManager(['test_entity_type' => $entity]);

    $this->fieldDefinitionListener->onFieldDefinitionCreate($field_definition->reveal());
  }

  /**
   * Tests on field definition create existing field.
   */
  public function testOnFieldDefinitionCreateExistingField(): void {
    $field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $field_definition->getTargetEntityTypeId()->willReturn('test_entity_type');
    $field_definition->getTargetBundle()->willReturn('test_bundle');
    $field_definition->getName()->willReturn('test_field');

    $storage = $this->prophesize(DynamicallyFieldableEntityStorageInterface::class);
    $storage->onFieldDefinitionCreate($field_definition->reveal())->shouldBeCalledTimes(1);
    $this->entityTypeManager->getStorage('test_entity_type')->willReturn($storage->reveal());

    $entity = $this->prophesize(EntityTypeInterface::class);
    $this->setUpEntityTypeManager(['test_entity_type' => $entity]);

    $this->fieldDefinitionListener->onFieldDefinitionCreate($field_definition->reveal());
  }

  /**
   * Tests on field definition update.
   */
  public function testOnFieldDefinitionUpdate(): void {
    $field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $field_definition->getTargetEntityTypeId()->willReturn('test_entity_type');

    $storage = $this->prophesize(DynamicallyFieldableEntityStorageInterface::class);
    $storage->onFieldDefinitionUpdate($field_definition->reveal(), $field_definition->reveal())->shouldBeCalledTimes(1);
    $this->entityTypeManager->getStorage('test_entity_type')->willReturn($storage->reveal());

    $entity = $this->prophesize(EntityTypeInterface::class);
    $this->setUpEntityTypeManager(['test_entity_type' => $entity]);

    $this->fieldDefinitionListener->onFieldDefinitionUpdate($field_definition->reveal(), $field_definition->reveal());
  }

  /**
   * Tests on field definition delete multiple bundles.
   */
  public function testOnFieldDefinitionDeleteMultipleBundles(): void {
    $field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $field_definition->getTargetEntityTypeId()->willReturn('test_entity_type');
    $field_definition->getTargetBundle()->willReturn('test_bundle');
    $field_definition->getName()->willReturn('test_field');

    $storage = $this->prophesize(DynamicallyFieldableEntityStorageInterface::class);
    $storage->onFieldDefinitionDelete($field_definition->reveal())->shouldBeCalledTimes(1);
    $this->entityTypeManager->getStorage('test_entity_type')->willReturn($storage->reveal());

    $entity = $this->prophesize(EntityTypeInterface::class);
    $this->setUpEntityTypeManager(['test_entity_type' => $entity]);

    $this->fieldDefinitionListener->onFieldDefinitionDelete($field_definition->reveal());
  }

  /**
   * Tests on field definition delete single bundles.
   */
  public function testOnFieldDefinitionDeleteSingleBundles(): void {
    $field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $field_definition->getTargetEntityTypeId()->willReturn('test_entity_type');
    $field_definition->getTargetBundle()->willReturn('test_bundle');
    $field_definition->getName()->willReturn('test_field');

    $storage = $this->prophesize(DynamicallyFieldableEntityStorageInterface::class);
    $storage->onFieldDefinitionDelete($field_definition->reveal())->shouldBeCalledTimes(1);
    $this->entityTypeManager->getStorage('test_entity_type')->willReturn($storage->reveal());

    $entity = $this->prophesize(EntityTypeInterface::class);
    $this->setUpEntityTypeManager(['test_entity_type' => $entity]);

    $this->fieldDefinitionListener->onFieldDefinitionDelete($field_definition->reveal());
  }

}
