<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Field;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteTagsWidget;
use Drupal\Core\Field\TypedData\FieldItemDataDefinition;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Tests violation preparation preserves widget error reporting.
 */
#[Group('Field')]
final class WidgetViolationPreparationTest extends UnitTestCase {

  /**
   * Tests extraction preserves item deltas before removing empty values.
   */
  #[DataProvider('extractionCases')]
  public function testExtractFormValues(bool $multiple, ?array $values, array $expected_values, array $expected_deltas, ?string $widget_class = NULL): void {
    $storage = $this->createStub(FieldStorageDefinitionInterface::class);
    $storage->method('getPropertyDefinitions')->willReturn(['value' => DataDefinition::create('string')]);
    $storage->method('getMainPropertyName')->willReturn('value');
    $definition = $this->createStub(FieldDefinitionInterface::class);
    $definition->method('getName')->willReturn('reference');
    $definition->method('getType')->willReturn('string');
    $definition->method('getFieldStorageDefinition')->willReturn($storage);
    $item_definition = FieldItemDataDefinition::create($definition);
    $definition->method('getItemDefinition')->willReturn($item_definition);
    $manager = $this->createStub(FieldTypePluginManagerInterface::class);
    $manager->method('createFieldItem')->willReturnCallback(static function (FieldItemListInterface $items, $delta, $value) use ($item_definition): StringItem {
      $item = new StringItem($item_definition, $delta, $items);
      $item->setValue($value, FALSE);
      return $item;
    });
    $typed_data = $this->createStub(TypedDataManagerInterface::class);
    $typed_data->method('getPropertyInstance')->willReturnCallback(static function ($parent, $name, $value): StringData {
      $property = new StringData(DataDefinition::create('string'), $name, $parent);
      $property->setValue($value, FALSE);
      return $property;
    });
    $container = new ContainerBuilder();
    $container->set('typed_data_manager', $typed_data);
    $container->set('plugin.manager.field.field_type', $manager);
    \Drupal::setContainer($container);

    if ($widget_class) {
      $widget = new $widget_class('test', ['multiple_values' => $multiple], $definition, [], []);
    }
    else {
      $widget = $this->getMockBuilder(WidgetBase::class)
        ->setConstructorArgs(['test', ['multiple_values' => $multiple], $definition, [], []])
        ->onlyMethods(['formElement', 'massageFormValues'])
        ->getMock();
      $widget->expects(self::once())->method('massageFormValues')->willReturnCallback(static fn(array $submitted) => $multiple ? $values : $submitted);
    }

    $items = $this->getMockBuilder(FieldItemList::class)
      ->setConstructorArgs([$definition])
      ->onlyMethods(['onChange'])
      ->getMock();
    $items->expects(self::never())->method('onChange');
    $state = new FormState();
    WidgetBase::setWidgetState(['host'], 'reference', $state, []);
    $state->setValues(['host' => ['reference' => $multiple ? [($widget_class ? 'target_id' : 'selection') => $values] : $values]]);
    $widget->extractFormValues($items, ['#parents' => ['host']], $state);

    self::assertSame($expected_values, $items->getValue());
    self::assertSame($expected_deltas, WidgetBase::getWidgetState(['host'], 'reference', $state)['original_deltas'] ?? []);
    foreach ($items as $item) {
      self::assertNull($item->_original_delta);
      self::assertNull($item->_weight);
    }
  }

  /**
   * Provides empty values, scalar items, supplied deltas and reordered inputs.
   */
  public static function extractionCases(): iterable {
    yield 'null list' => [TRUE, NULL, [], []];
    yield 'actual tags widget with null' => [TRUE, NULL, [], [], EntityReferenceAutocompleteTagsWidget::class];
    yield 'actual tags widget with sparse values' => [
      TRUE,
      [2 => ['value' => 'A'], 5 => ['value' => ''], 9 => ['value' => 'B']],
      [['value' => 'A'], ['value' => 'B']],
      [2, 9],
      EntityReferenceAutocompleteTagsWidget::class,
    ];
    yield 'empty list' => [TRUE, [], [], []];
    yield 'one item' => [TRUE, [['value' => 'A']], [['value' => 'A']], [0]];
    yield 'sparse deltas and empty item' => [
      TRUE,
      [2 => ['value' => 'A'], 5 => ['value' => ''], 9 => ['value' => 'B']],
      [['value' => 'A'], ['value' => 'B']],
      [2, 9],
    ];
    yield 'leading and trailing empty items' => [
      TRUE,
      [2 => [], 4 => ['value' => 'A'], 8 => []],
      [['value' => 'A']],
      [4],
    ];
    yield 'supplied deltas' => [
      TRUE,
      [3 => ['value' => 'A', '_original_delta' => 12], 8 => ['value' => 'B', '_original_delta' => 0]],
      [['value' => 'A'], ['value' => 'B']],
      [12, 0],
    ];
    yield 'repeated values' => [
      TRUE,
      [2 => ['value' => 'A'], 7 => ['value' => 'A']],
      [['value' => 'A'], ['value' => 'A']],
      [2, 7],
    ];
    yield 'scalar items' => [TRUE, [2 => 'A', 5 => '', 9 => 'B'], [['value' => 'A'], ['value' => 'B']], [2, 9]];
    yield 'zero scalar' => [TRUE, [4 => '0'], [['value' => '0']], [4]];
    yield 'single inputs reordered' => [
      FALSE,
      [0 => ['value' => 'A', '_weight' => 10], 1 => ['value' => 'B', '_weight' => -10]],
      [['value' => 'B'], ['value' => 'A']],
      [1, 0],
    ];
    yield 'single inputs with empty item' => [
      FALSE,
      [0 => ['value' => '', '_weight' => 0], 1 => ['value' => 'B', '_weight' => 1]],
      [['value' => 'B']],
      [1],
    ];
  }

  /**
   * Provides default and overridden preparation.
   */
  public static function preparationCases(): iterable {
    yield 'default preparation' => [FALSE];
    yield 'rewritten messages' => [TRUE];
  }

  /**
   * Tests preparation preserves callback order and error-dependent routing.
   */
  #[DataProvider('preparationCases')]
  public function testErrorReporting(bool $rewrite): void {
    $definition = $this->createStub(FieldDefinitionInterface::class);
    $definition->method('getName')->willReturn('reference');
    $methods = ['formElement', 'errorElement'];
    if ($rewrite) {
      $methods[] = 'prepareViolations';
    }
    $widget = $this->getMockBuilder(WidgetBase::class)
      ->setConstructorArgs(['test', ['multiple_values' => TRUE], $definition, [], []])
      ->onlyMethods($methods)
      ->getMock();
    $state = new PreparationTestFormState();
    $form = [
      '#parents' => [],
      'reference' => [
        '#parents' => ['reference'],
        'left' => ['#parents' => ['reference', 'left']],
        'right' => ['#parents' => ['reference', 'right']],
      ],
    ];
    $state->setCompleteForm($form);
    WidgetBase::setWidgetState([], 'reference', $state, ['array_parents' => ['reference']]);
    $violations = new ConstraintViolationList([
      new ConstraintViolation('A', 'A', [], NULL, '0.left', 42),
      new ConstraintViolation('B', 'B', [], NULL, '1.right', 43),
      new ConstraintViolation('C', 'C', [], NULL, '2.left', 44),
    ]);
    if ($rewrite) {
      $widget->expects(self::once())->method('prepareViolations')->with($violations, $form['reference'], [])
        ->willReturnCallback(static function (ConstraintViolationListInterface $list): ConstraintViolationListInterface {
          $prepared = new ConstraintViolationList();
          foreach ($list as $violation) {
            $prepared->add(new ConstraintViolation('Prepared ' . $violation->getMessage(), $violation->getMessageTemplate(), [], NULL, $violation->getPropertyPath(), $violation->getInvalidValue()));
          }
          return $prepared;
        });
    }
    $widget->expects(self::exactly(3))->method('errorElement')->willReturnCallback(static function (array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $state): array {
      $errors = count($state->getErrors());
      $state->trace[] = ['resolve', $error->getMessage(), $errors];
      return $element[$errors ? 'right' : 'left'];
    });
    $widget->flagErrors($this->createStub(FieldItemListInterface::class), $violations, $form, $state);
    $prefix = $rewrite ? 'Prepared ' : '';
    self::assertSame([
      ['resolve', $prefix . 'A', 0], ['report', $prefix . 'A', 0],
      ['resolve', $prefix . 'B', 1], ['report', $prefix . 'B', 1],
      ['resolve', $prefix . 'C', 2], ['report', $prefix . 'C', 2],
    ], $state->trace);
    self::assertSame([
      'reference][left' => $prefix . 'A',
      'reference][right' => $prefix . 'B',
    ], $state->getErrors());
  }

}

/**
 * Records calls before FormState applies its first-error rule.
 */
final class PreparationTestFormState extends FormState {

  /**
   * Error-element resolution and reporting calls.
   *
   * @var array
   */
  public array $trace = [];

  /**
   * {@inheritdoc}
   */
  public function setError(array &$element, $message = '') {
    $this->trace[] = ['report', $message, count($this->getErrors())];
    return parent::setError($element, $message);
  }

}
