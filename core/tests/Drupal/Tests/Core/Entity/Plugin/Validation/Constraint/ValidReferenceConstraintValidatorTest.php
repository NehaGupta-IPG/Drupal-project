<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Entity\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ClassResolver;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
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
 * Tests reference validation using parentless field lists.
 */
#[CoversClass(ValidReferenceConstraintValidator::class)]
#[CoversClass(ValidReferenceConstraint::class)]
#[Group('Validation')]
class ValidReferenceConstraintValidatorTest extends UnitTestCase {

  /**
   * Preserves handler identity, deltas, violation metadata, and custom messages.
   */
  #[DataProvider('newTargetsProvider')]
  public function testNewTargets(bool $accepted, bool $allow_new, ?string $custom_message): void {
    $constraint = new ValidReferenceConstraint();
    if ($custom_message !== NULL) {
      $constraint->invalidAutocreateMessage = $custom_message;
    }
    $entity = $this->createStub(EntityInterface::class);
    $entity->method('isNew')->willReturn(TRUE);
    $entity->method('label')->willReturn('New <page>');
    $entities = [7 => $entity];
    $handler = $this->createMock(DefaultSelection::class);
    $handler->expects($allow_new ? $this->once() : $this->never())
      ->method('validateReferenceableNewEntities')->with($entities)
      ->willReturn($accepted ? $entities : []);
    $invalid = !$accepted || !$allow_new;
    $handler->expects($this->never())->method('getReferenceableEntities');
    $handler->expects($this->never())->method('validateReferenceableEntities');
    $selection_manager = $this->createMock(SelectionPluginManagerInterface::class);
    $selection_manager->expects($this->never())->method('getInstance');
    $selection_manager->expects($this->never())->method('getSelectionHandler');
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects($this->never())->method('getDefinition');
    $manager->expects($this->never())->method('getStorage');
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $manager);
    $container->set('plugin.manager.entity_reference_selection', $selection_manager);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
    $validator = (new BasicRecursiveValidatorFactory(new ClassResolver($container)))->createValidator();
    $item = $this->createStub(EntityReferenceItem::class);
    $item->method(PropertyHook::get('target_id'))->willReturn(NULL);
    $item->method(PropertyHook::get('entity'))->willReturn($entity);
    $item->method('isEmpty')->willReturn(FALSE);
    $definition = $this->createStub(FieldDefinitionInterface::class);
    $definition->method('getSetting')->willReturn('node');
    $context = $this->createStub(FieldItemList::class);
    $context->method('getFieldDefinition')->willReturn($definition);
    $context->method('getIterator')->willReturn(new \ArrayIterator([7 => $item]));
    $context->method('getParent')->willReturn(NULL);
    $constraint->handler = $handler;
    $constraint->allowNewEntities = $allow_new;
    $violations = $validator->validate($context, $constraint);
    $this->assertCount($invalid ? 1 : 0, $violations);
    if ($invalid) {
      $violation = $violations[0];
      $this->assertSame('7.entity', $violation->getPropertyPath());
      $this->assertSame($entity, $violation->getInvalidValue());
      $this->assertSame($constraint, $violation->getConstraint());
      $this->assertSame($context, $violation->getRoot());
      $expected = $constraint->invalidAutocreateMessage;
      $this->assertSame($expected, $violation->getMessageTemplate());
      $parameters = ['%type' => 'node', '%label' => 'New <page>'];
      $this->assertSame($parameters, $violation->getParameters());
      $this->assertStringNotContainsString('<page>', (string) $violation->getMessage());
    }
  }

  /**
   * Provides acceptance, rejection, explicit disabling, and customization cases.
   */
  public static function newTargetsProvider(): array {
    return [
      'accepted' => [TRUE, TRUE, NULL],
      'rejected' => [FALSE, TRUE, NULL],
      'disabled input rejects an accepting handler' => [TRUE, FALSE, NULL],
      'custom rejection' => [FALSE, TRUE, 'Custom %label.'],
    ];
  }

  /**
   * Keeps the old positional constructor and inherited message properties.
   */
  public function testExistingConstraintConstructor(): void {
    $constraint = new class('existing', 'missing', 'new', 'null', ['legacy'], 'payload') extends ValidReferenceConstraint {};
    $this->assertSame('existing', $constraint->message);
    $this->assertSame('missing', $constraint->nonExistingMessage);
    $this->assertSame('new', $constraint->invalidAutocreateMessage);
    $this->assertSame('null', $constraint->nullMessage);
    $this->assertSame(['legacy'], $constraint->groups);
    $this->assertSame('payload', $constraint->payload);
    $constructor = new \ReflectionMethod(ValidReferenceConstraintValidator::class, '__construct');
    $this->assertCount(2, $constructor->getParameters());
  }

}
