<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Entity;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityReferenceMessageFormatter;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraint;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteTagsWidget;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Tests entity reference messages and their input context.
 */
#[Group('Entity')]
final class EntityReferenceMessageFormatterTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    $definition = $this->createStub(EntityTypeInterface::class);
    $definition->method('getSingularLabel')->willReturn('content item');
    $manager = $this->createStub(EntityTypeManagerInterface::class);
    $manager->method('getDefinition')->willReturn($definition);
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $manager);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Provides standard reference rejection messages.
   */
  public static function referenceMessages(): iterable {
    yield 'existing target' => ['message', ValidReferenceConstraint::EXISTING_ENTITY_NOT_REFERENCEABLE_ERROR];
    yield 'missing target' => ['nonExistingMessage', ValidReferenceConstraint::ENTITY_DOES_NOT_EXIST_ERROR];
    yield 'new target' => ['invalidAutocreateMessage', ValidReferenceConstraint::NEW_ENTITY_NOT_REFERENCEABLE_ERROR];
  }

  /**
   * Tests input adapts messages while diagnostics and metadata are preserved.
   */
  #[DataProvider('referenceMessages')]
  public function testMetadataAndOriginalViolationArePreserved(string $message_property, string $code): void {
    $constraint = new ValidReferenceConstraint();
    $cause = new \stdClass();
    $root = new \stdClass();
    $original = new ConstraintViolation('Original', $constraint->$message_property, ['%id' => 42], $root, '0.target_id', 42, NULL, $code, $constraint, $cause);
    $constraint->noReferenceableMatchMessage = 'Cannot reference %value as @entity_type.';
    $list = new ConstraintViolationList([$original]);
    self::assertSame($list, EntityReferenceMessageFormatter::formatViolations($list, 'content item', []));
    self::assertSame($original, EntityReferenceMessageFormatter::formatViolations($list, 'content item', [1 => 'Other input'])[0]);
    $result = EntityReferenceMessageFormatter::formatViolations($list, 'content item', ['Example (42)']);
    $formatted = $result[0];
    self::assertSame('Cannot reference <em class="placeholder">Example (42)</em> as content item.', (string) $formatted->getMessage());
    self::assertSame('Original', $original->getMessage());
    self::assertNotSame($original, $formatted);
    foreach (['getRoot', 'getPropertyPath', 'getInvalidValue', 'getPlural', 'getCode', 'getConstraint', 'getCause'] as $method) {
      self::assertSame($original->$method(), $formatted->$method());
    }
    self::assertSame(42, $formatted->getParameters()['%id']);
  }

  /**
   * Tests custom and unrelated messages remain untouched.
   */
  public function testCustomAndUnrelatedMessagesRemainUntouched(): void {
    $constraint = new ValidReferenceConstraint();
    $constraint->message = 'Custom rejection for %id.';
    $custom = new ConstraintViolation('Custom rejection', $constraint->message, [], NULL, '0.target_id', 42, NULL, ValidReferenceConstraint::EXISTING_ENTITY_NOT_REFERENCEABLE_ERROR, $constraint);
    $unrelated = new ConstraintViolation('Other validation', 'Other validation', [], NULL, '0.entity', NULL);
    $list = new ConstraintViolationList([$custom, $unrelated]);
    $formatted = EntityReferenceMessageFormatter::formatViolations($list, 'content item', ['Example (42)']);
    self::assertSame($custom, $formatted[0]);
    self::assertSame($unrelated, $formatted[1]);

    $constraint->noReferenceableMatchMessage = 'Custom input rejection: %value.';
    $constraint->noReferenceableSelectionMessage = 'Custom selected @entity_type rejection.';
    self::assertSame('Custom input rejection: <em class="placeholder">Example</em>.', (string) EntityReferenceMessageFormatter::formatMessage('content item', 'Example', $constraint));
    self::assertSame('Custom selected content item rejection.', (string) EntityReferenceMessageFormatter::formatMessage('content item', NULL, $constraint));
  }

  /**
   * Tests five actual widget inputs keep five original tokens.
   */
  public function testFiveActualWidgetInputsKeepFiveOriginalTokens(): void {
    [$widget, $items, $form, $state] = $this->fixture(FALSE, range(0, 4));
    $widget->flagErrors($items, $this->violations(range(0, 4)), $form, $state);
    self::assertCount(5, $state->getErrors());
    foreach (array_values($state->getErrors()) as $delta => $message) {
      self::assertStringContainsString('>Example ' . $delta . ' (42)</em>', (string) $message);
    }
  }

  /**
   * Tests actual tags widget retains first message for single input.
   */
  public function testActualTagsWidgetRetainsFirstMessageForSingleInput(): void {
    [$widget, $items, $form, $state] = $this->fixture(TRUE, [2, 0]);
    $widget->flagErrors($items, $this->violations([0, 1]), $form, $state);
    self::assertCount(1, $state->getErrors());
    self::assertCount(2, $state->reported);
    self::assertStringContainsString('>Example 2 (42)</em>', (string) $state->reported[0]);
    self::assertStringContainsString('>Example 0 (42)</em>', (string) $state->reported[1]);
  }

  /**
   * Builds the test fixture.
   */
  private function fixture(bool $tags, array $original_deltas): array {
    $definition = $this->createStub(FieldDefinitionInterface::class);
    $definition->method('getName')->willReturn('reference');
    $definition->method('getSetting')->willReturnMap([['target_type', 'node']]);
    $class = $tags ? EntityReferenceAutocompleteTagsWidget::class : EntityReferenceAutocompleteWidget::class;
    $widget = new $class('entity_reference_autocomplete', ['multiple_values' => $tags], $definition, [], []);
    $state = new ReferenceMessageRecordingFormState();
    $form = ['#parents' => [], 'reference' => ['#parents' => ['reference']]];
    if ($tags) {
      $form['reference']['target_id'] = [
        '#parents' => ['reference', 'target_id'],
        '#tags' => TRUE,
        '#entity_reference_input_values' => ['Example 0 (42)', 'Example 1 (42)', 'Example 2 (42)'],
      ];
    }
    else {
      foreach ($original_deltas as $delta) {
        $form['reference'][$delta]['target_id'] = [
          '#parents' => ['reference', $delta, 'target_id'],
          '#entity_reference_input_values' => ['Example ' . $delta . ' (42)'],
        ];
      }
    }
    $state->setCompleteForm($form);
    WidgetBase::setWidgetState([], 'reference', $state, [
      'array_parents' => ['reference'],
      'original_deltas' => $original_deltas,
    ]);
    $items = $this->createStub(FieldItemList::class);
    $items->method('getIterator')->willReturnCallback(static fn() => new \ArrayIterator(array_fill(0, count($original_deltas), new \stdClass())));
    return [$widget, $items, $form, $state];
  }

  /**
   * Creates reference violations.
   */
  private function violations(array $deltas): ConstraintViolationList {
    $constraint = new ValidReferenceConstraint();
    $list = new ConstraintViolationList();
    foreach ($deltas as $delta) {
      $list->add(new ConstraintViolation('Original', $constraint->message, [], NULL, $delta . '.target_id', 42, NULL, ValidReferenceConstraint::EXISTING_ENTITY_NOT_REFERENCEABLE_ERROR, $constraint));
    }
    return $list;
  }

}

/**
 * Records every error report before FormState applies its first-error rule.
 */
final class ReferenceMessageRecordingFormState extends FormState {

  /**
   * Reported messages, including subsequent messages for the same input.
   *
   * @var array
   */
  public array $reported = [];

  /**
   * {@inheritdoc}
   */
  public function setError(array &$element, $message = '') {
    $this->reported[] = $message;
    return parent::setError($element, $message);
  }

}
