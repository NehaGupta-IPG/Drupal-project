<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Entity\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ClassResolver;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraint;
use Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraintValidator;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Validation\BasicRecursiveValidatorFactory;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Runtime\PropertyHook;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests reference violations follow item order while retaining batch checks.
 */
#[CoversClass(ValidReferenceConstraintValidator::class)]
#[Group('Validation')]
class ValidReferenceOrderTest extends UnitTestCase {

  /**
   * Reports mixed reference failures in their original field item order.
   */
  #[DataProvider('referenceOrderProvider')]
  public function testReferenceOrder(array $values): void {
    $items = [];
    $new_entities = [];
    $target_ids = [];
    $paths = [];
    $invalid_values = [];
    foreach ($values as $delta => $target_id) {
      $item = $this->createStub(EntityReferenceItem::class);
      $item->method(PropertyHook::get('target_id'))->willReturn($target_id);
      if ($target_id === NULL) {
        $entity = $this->createStub(EntityInterface::class);
        $entity->method('isNew')->willReturn(TRUE);
        $entity->method('label')->willReturn('New target ' . $delta);
        $item->method('isEmpty')->willReturn(FALSE);
        $item->method(PropertyHook::get('entity'))->willReturn($entity);
        $new_entities[$delta] = $entity;
        $paths[] = $delta . '.entity';
        $invalid_values[] = $entity;
      }
      else {
        $target_ids[$delta] = $target_id;
        $paths[] = $delta . '.target_id';
        $invalid_values[] = $target_id;
      }
      $items[$delta] = $item;
    }
    $field = $this->createStub(FieldDefinitionInterface::class);
    $field->method('getSetting')->willReturn('node');
    $list = $this->createStub(FieldItemList::class);
    $list->method('getFieldDefinition')->willReturn($field);
    $list->method('getIterator')->willReturn(new \ArrayIterator($items));
    $list->method('getParent')->willReturn(NULL);
    $handler = $this->createMock(DefaultSelection::class);
    $handler->expects($this->once())->method('validateReferenceableNewEntities')->with($new_entities)->willReturn([]);
    $handler->expects($this->once())->method('validateReferenceableEntities')->with($target_ids)->willReturn([]);
    $selection = $this->createMock(SelectionPluginManagerInterface::class);
    $selection->expects($this->once())->method('getSelectionHandler')->with($field, NULL)->willReturn($handler);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('loadMultiple')->with($target_ids)->willReturn([]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects($this->once())->method('getStorage')->with('node')->willReturn($storage);
    $container = new ContainerBuilder();
    $container->set('plugin.manager.entity_reference_selection', $selection);
    $container->set('entity_type.manager', $manager);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
    $validator = (new BasicRecursiveValidatorFactory(new ClassResolver($container)))->createValidator();
    $constraint = new ValidReferenceConstraint();
    $violations = $validator->validate($list, $constraint);
    $this->assertCount(count($values), $violations);
    foreach ($violations as $index => $violation) {
      $this->assertSame($paths[$index], $violation->getPropertyPath());
      $this->assertSame($invalid_values[$index], $violation->getInvalidValue());
      $this->assertSame($list, $violation->getRoot());
      $this->assertSame($constraint, $violation->getConstraint());
    }
  }

  /**
   * Provides mixed targets, sparse deltas and repeated existing references.
   */
  public static function referenceOrderProvider(): array {
    return [
      'existing then new' => [[101, NULL]],
      'new then existing' => [[NULL, 101]],
      'interleaved sparse deltas' => [[2 => 101, 5 => NULL, 9 => 102, 12 => NULL]],
      'repeated existing target' => [[1 => 101, 4 => NULL, 8 => 101]],
    ];
  }

}
