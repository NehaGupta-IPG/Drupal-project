<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Field;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Field\FieldItemList.
 */
#[CoversClass(FieldItemList::class)]
#[Group('Field')]
class FieldItemListTest extends UnitTestCase {

  /**
   * Tests equals.
   */
  #[DataProvider('providerTestEquals')]
  public function testEquals($expected, ?FieldItemInterface $first_field_item = NULL, ?FieldItemInterface $second_field_item = NULL): void {

    // Mock the field type manager and place it in the container.
    $field_type_manager = $this->createStub(FieldTypePluginManagerInterface::class);
    $container = new ContainerBuilder();
    $container->set('plugin.manager.field.field_type', $field_type_manager);
    \Drupal::setContainer($container);

    // Set up three properties, one of them being computed.
    $property_definitions['0'] = $this->createStub(DataDefinitionInterface::class);
    $property_definitions['0']
      ->method('isComputed')
      ->willReturn(FALSE);
    $property_definitions['1'] = $this->createStub(DataDefinitionInterface::class);
    $property_definitions['1']
      ->method('isComputed')
      ->willReturn(FALSE);
    $property_definitions['2'] = $this->createStub(DataDefinitionInterface::class);
    $property_definitions['2']
      ->method('isComputed')
      ->willReturn(TRUE);

    $field_storage_definition = $this->createStub(FieldStorageDefinitionInterface::class);
    $field_storage_definition
      ->method('getPropertyDefinitions')
      ->willReturn($property_definitions);
    $field_definition = $this->createStub(FieldDefinitionInterface::class);
    $field_definition
      ->method('getFieldStorageDefinition')
      ->willReturn($field_storage_definition);

    $field_list_a = new FieldItemList($field_definition);
    $field_list_b = new FieldItemList($field_definition);

    // Set up the mocking necessary for creating field items.
    $field_type_manager
      ->method('createFieldItem')
      ->willReturnOnConsecutiveCalls($first_field_item, $second_field_item);

    // Set the field item values.
    if ($first_field_item instanceof FieldItemInterface) {
      $field_list_a->setValue($first_field_item);
    }
    if ($second_field_item instanceof FieldItemInterface) {
      $field_list_b->setValue($second_field_item);
    }

    $this->assertEquals($expected, $field_list_a->equals($field_list_b));
  }

  /**
   * Data provider for testEquals.
   */
  public static function providerTestEquals(): array {
    // Tests field item lists with no values.
    $datasets[] = [TRUE];

    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_a */
    $field_item_a = new FieldItemTestClass();
    $field_item_a->setValue([1]);
    // Tests field item lists where one has a value and one does not.
    $datasets[] = [FALSE, $field_item_a];

    // Tests field item lists where both have the same value.
    $datasets[] = [TRUE, $field_item_a, $field_item_a];

    /** @var \Drupal\Core\Field\FieldItemBase  $fv */
    $field_item_b = new FieldItemTestClass();
    $field_item_b->setValue([2]);
    // Tests field item lists where both have the different values.
    $datasets[] = [FALSE, $field_item_a, $field_item_b];

    /** @var \Drupal\Core\Field\FieldItemBase  $fv */
    $field_item_c = new FieldItemTestClass();
    $field_item_c->setValue(['0' => 1, '1' => 2]);
    $field_item_d = new FieldItemTestClass();
    $field_item_d->setValue(['1' => 2, '0' => 1]);

    // Tests field item lists where both have the differently ordered values.
    $datasets[] = [TRUE, $field_item_c, $field_item_d];

    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_e */
    $field_item_e = new FieldItemTestClass();
    $field_item_e->setValue(['2']);

    // Tests field item lists where both have same values but different data
    // types.
    $datasets[] = [TRUE, $field_item_b, $field_item_e];

    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_f */
    $field_item_f = new FieldItemTestClass();
    $field_item_f->setValue(['0' => 1, '1' => 2, '2' => 3]);
    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_g */
    $field_item_g = new FieldItemTestClass();
    $field_item_g->setValue(['0' => 1, '1' => 2, '2' => 4]);

    // Tests field item lists where both have same values for the non-computed
    // properties ('0' and '1') and a different value for the computed one
    // ('2').
    $datasets[] = [TRUE, $field_item_f, $field_item_g];

    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_h */
    $field_item_h = new FieldItemTestClass();
    $field_item_h->setValue(['0' => 1, '1' => 2, '3' => 3]);
    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_i */
    $field_item_i = new FieldItemTestClass();
    $field_item_i->setValue(['0' => 1, '1' => 2, '3' => 4]);

    // Tests field item lists where both have same values for the non-computed
    // properties ('0' and '1') and a different value for a property that does
    // not exist ('3').
    $datasets[] = [TRUE, $field_item_h, $field_item_i];

    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_o */
    $field_item_o = new FieldItemTestClass();
    $field_item_o->setValue(['+2']);

    // Tests field item lists where both have same values but different data
    // types with leading plus.
    $datasets[] = [FALSE, $field_item_e, $field_item_o];

    // Tests field item lists where both have different data type
    // and different data.
    $datasets[] = [FALSE, $field_item_b, $field_item_o];

    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_p */
    $field_item_p = new FieldItemTestClass();
    $field_item_p->setValue(['02']);

    // Tests field item lists where string values differ.
    $datasets[] = [FALSE, $field_item_o, $field_item_p];

    // Tests field item lists where both have different data type
    // and different data.
    $datasets[] = [FALSE, $field_item_b, $field_item_p];

    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_q */
    $field_item_q = new FieldItemTestClass();
    $field_item_q->setValue([TRUE]);

    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_r */
    $field_item_r = new FieldItemTestClass();
    $field_item_r->setValue(['true']);

    // Tests field item lists where boolean converted to string values differ.
    $datasets[] = [TRUE, $field_item_q, $field_item_r];

    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_s */
    $field_item_s = new FieldItemTestClass();
    $field_item_s->setValue([FALSE]);

    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_t */
    $field_item_t = new FieldItemTestClass();
    $field_item_t->setValue([0]);

    // Tests field item lists where converted to string values differ.
    $datasets[] = [TRUE, $field_item_s, $field_item_t];

    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_u */
    $field_item_u = new FieldItemTestClass();
    $field_item_u->setValue(['1']);

    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_v */
    $field_item_v = new FieldItemTestClass();
    $field_item_v->setValue([1]);

    // Tests field item lists where boolean compared with text is equal.
    $datasets[] = [TRUE, $field_item_q, $field_item_u];
    // Tests field item lists where boolean compared with int is equal.
    $datasets[] = [TRUE, $field_item_q, $field_item_v];

    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_w */
    $field_item_w = new FieldItemTestClass();
    $field_item_w->setValue(['']);

    // Tests field item lists where boolean compared with text is equal.
    $datasets[] = [TRUE, $field_item_s, $field_item_w];
    // Tests field item lists where boolean compared with int differs.
    $datasets[] = [FALSE, $field_item_t, $field_item_w];

    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_x */
    $field_item_x = new FieldItemTestClass();
    $field_item_x->setValue([
      0 => [
        'filter_format' => 'HTML',
        'value' => 2,
        'extra' => 'something',
      ],
    ]);

    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_y */
    $field_item_y = new FieldItemTestClass();
    $field_item_y->setValue([
      0 => [
        'filter_format' => 'HTML',
        'value' => '02',
        'extra' => 'something',
      ],
    ]);

    // Tests field item lists where array compared with another unequal array.
    $datasets[] = [FALSE, $field_item_x, $field_item_y];

    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_j */
    $field_item_j = new FieldItemTestClass();
    $field_item_j->setValue(['0' => 1]);
    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_k */
    $field_item_k = new FieldItemTestClass();
    $field_item_k->setValue(['0' => 1, '1' => NULL]);
    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_l */
    $field_item_l = new FieldItemTestClass();
    $field_item_l->setValue(['0' => 1, '1' => FALSE]);
    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_m */
    $field_item_m = new FieldItemTestClass();
    $field_item_m->setValue(['0' => 1, '1' => '']);
    /** @var \Drupal\Core\Field\FieldItemBase  $field_item_n */
    $field_item_n = new FieldItemTestClass();
    $field_item_n->setValue(['0' => 1, '1' => '2', '2' => '3']);

    // Tests filter properties with a NULL value. Empty strings or other false-y
    // values are not filtered.
    $datasets[] = [TRUE, $field_item_j, $field_item_k];
    $datasets[] = [FALSE, $field_item_j, $field_item_l];
    $datasets[] = [FALSE, $field_item_j, $field_item_m];
    $datasets[] = [FALSE, $field_item_n, $field_item_j];

    return $datasets;
  }

  /**
   * Tests identical behavior of ::hasAffectingChanges with ::equals.
   */
  #[DataProvider('providerTestEquals')]
  public function testHasAffectingChanges($expected, ?FieldItemInterface $first_field_item = NULL, ?FieldItemInterface $second_field_item = NULL): void {
    // Mock the field type manager and place it in the container.
    $field_type_manager = $this->createStub(FieldTypePluginManagerInterface::class);
    $container = new ContainerBuilder();
    $container->set('plugin.manager.field.field_type', $field_type_manager);
    \Drupal::setContainer($container);

    $field_storage_definition = $this->createStub(FieldStorageDefinitionInterface::class);
    $field_storage_definition
      ->method('getColumns')
      ->willReturn([0 => '0', 1 => '1']);

    // Set up three properties, one of them being computed.
    $property_definitions['0'] = $this->createStub(DataDefinitionInterface::class);
    $property_definitions['0']
      ->method('isComputed')
      ->willReturn(FALSE);
    $property_definitions['1'] = $this->createStub(DataDefinitionInterface::class);
    $property_definitions['1']
      ->method('isComputed')
      ->willReturn(FALSE);
    $property_definitions['2'] = $this->createStub(DataDefinitionInterface::class);
    $property_definitions['2']
      ->method('isComputed')
      ->willReturn(TRUE);

    $field_storage_definition = $this->createStub(FieldStorageDefinitionInterface::class);
    $field_storage_definition
      ->method('getPropertyDefinitions')
      ->willReturn($property_definitions);

    $field_definition = $this->createStub(FieldDefinitionInterface::class);
    $field_definition
      ->method('getFieldStorageDefinition')
      ->willReturn($field_storage_definition);
    $field_definition
      ->method('isComputed')
      ->willReturn(FALSE);

    $field_list_a = new FieldItemList($field_definition);
    $field_list_b = new FieldItemList($field_definition);

    // Set up the mocking necessary for creating field items.
    $field_type_manager
      ->method('createFieldItem')
      ->willReturnOnConsecutiveCalls($first_field_item, $second_field_item);

    // Set the field item values.
    if ($first_field_item instanceof FieldItemInterface) {
      $field_list_a->setValue($first_field_item);
    }
    if ($second_field_item instanceof FieldItemInterface) {
      $field_list_b->setValue($second_field_item);
    }

    $this->assertEquals($expected, !$field_list_a->hasAffectingChanges($field_list_b, ''));
  }

  /**
   * Tests equals empty items.
   */
  public function testEqualsEmptyItems(): void {
    /** @var \Drupal\Core\Field\FieldItemBase  $fv */
    $first_field_item = new FieldItemTestClass();
    $first_field_item->setValue(['0' => 1, '1' => 2]);
    $second_field_item = new FieldItemTestClass();
    $second_field_item->setValue(['1' => 2, '0' => 1]);
    $empty_field_item = new FieldItemTestClass();
    // Mock the field type manager and place it in the container.
    $field_type_manager = $this->createStub(FieldTypePluginManagerInterface::class);
    $container = new ContainerBuilder();
    $container->set('plugin.manager.field.field_type', $field_type_manager);
    \Drupal::setContainer($container);

    // Set up the properties of the field item.
    $property_definitions['0'] = $this->createStub(DataDefinitionInterface::class);
    $property_definitions['0']
      ->method('isComputed')
      ->willReturn(FALSE);
    $property_definitions['1'] = $this->createStub(DataDefinitionInterface::class);
    $property_definitions['1']
      ->method('isComputed')
      ->willReturn(FALSE);

    $field_storage_definition = $this->createStub(FieldStorageDefinitionInterface::class);
    $field_storage_definition
      ->method('getPropertyDefinitions')
      ->willReturn($property_definitions);
    $field_definition = $this->createStub(FieldDefinitionInterface::class);
    $field_definition
      ->method('getFieldStorageDefinition')
      ->willReturn($field_storage_definition);

    $field_list_a = new FieldItemList($field_definition);
    $field_list_b = new FieldItemList($field_definition);

    // Set up the mocking necessary for creating field items.
    $field_type_manager
      ->method('createFieldItem')
      ->willReturnOnConsecutiveCalls($first_field_item, $second_field_item, $empty_field_item, $empty_field_item);

    // Set the field item values.
    $field_list_a->setValue($first_field_item);
    $field_list_b->setValue($second_field_item);
    $field_list_a->appendItem($empty_field_item);

    // Field list A has an empty item.
    $this->assertEquals(FALSE, $field_list_a->equals($field_list_b));

    // Field lists A and B have empty items.
    $field_list_b->appendItem($empty_field_item);
    $this->assertEquals(TRUE, $field_list_a->equals($field_list_b));

    // Field list B has an empty item.
    $field_list_a->filterEmptyItems();
    $this->assertEquals(FALSE, $field_list_a->equals($field_list_b));

    // Neither field lists A and B have empty items.
    $field_list_b->filterEmptyItems();
    $this->assertEquals(TRUE, $field_list_a->equals($field_list_b));
  }

  /**
   * Tests default values form.
   */
  public function testDefaultValuesForm(): void {
    $field_definition = $this->createStub(FieldDefinitionInterface::class);
    $field_definition
      ->method('getType')
      ->willReturn('field_type');
    /** @var \Drupal\Core\Field\FieldItemList|\PHPUnit\Framework\MockObject\MockObject $field_list */
    $field_list = $this->getMockBuilder(FieldItemList::class)
      ->onlyMethods(['defaultValueWidget'])
      ->setConstructorArgs([$field_definition])
      ->getMock();
    $field_list->expects($this->once())
      ->method('defaultValueWidget')
      ->willReturn(NULL);
    $form = [];
    $form_state = new FormState();
    $string_translation = $this->getStringTranslationStub();
    $field_list->setStringTranslation($string_translation);

    $this->assertEquals('No widget available for: <em class="placeholder">field_type</em>.', $field_list->defaultValuesForm($form, $form_state)['#markup']);
  }

  /**
   * Tests default values form validate.
   */
  public function testDefaultValuesFormValidate(): void {
    $field_definition = $this->createStub(FieldDefinitionInterface::class);
    /** @var \Drupal\Core\Field\FieldItemList|\PHPUnit\Framework\MockObject\MockObject $field_list */
    $field_list = $this->getMockBuilder(FieldItemList::class)
      ->onlyMethods(['defaultValueWidget', 'validate'])
      ->setConstructorArgs([$field_definition])
      ->getMock();
    $field_list
      ->method('defaultValueWidget')
      ->willReturn(NULL);
    $field_list->expects($this->never())
      ->method('validate');
    $form = [];
    $form_state = new FormState();

    $field_list->defaultValuesFormValidate([], $form, $form_state);
  }

  /**
   * Tests default values form submit.
   */
  public function testDefaultValuesFormSubmit(): void {
    $field_definition = $this->createStub(FieldDefinitionInterface::class);
    /** @var \Drupal\Core\Field\FieldItemList|\PHPUnit\Framework\MockObject\MockObject $field_list */
    $field_list = $this->getMockBuilder(FieldItemList::class)
      ->onlyMethods(['defaultValueWidget', 'getValue'])
      ->setConstructorArgs([$field_definition])
      ->getMock();
    $field_list
      ->method('defaultValueWidget')
      ->willReturn(NULL);
    $form = [];
    $form_state = new FormState();
    $field_list->expects($this->never())
      ->method('getValue');

    $this->assertSame([], $field_list->defaultValuesFormSubmit([], $form, $form_state));
  }

}

/**
 * A class extending FieldItemBase for testing purposes.
 */
class FieldItemTestClass extends FieldItemBase {

  public function __construct() {
  }

  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    return [];
  }

  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [];
  }

}
