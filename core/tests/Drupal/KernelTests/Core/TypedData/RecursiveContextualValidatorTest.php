<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\TypedData;

use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\Core\TypedData\Validation\RecursiveContextualValidator;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal\Core\TypedData\Validation\RecursiveContextualValidator.
 */
#[CoversClass(RecursiveContextualValidator::class)]
#[Group('Validation')]
#[RunTestsInSeparateProcesses]
class RecursiveContextualValidatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
  }

  /**
   * Tests recursive validation against given constraints against an entity.
   */
  public function testRecursiveValidate(): void {
    $entity = EntityTest::create();
    $adapter = EntityAdapter::createFromEntity($entity);
    // This would trigger the ValidReferenceConstraint due to EntityTest
    // defaulting uid to 1, which doesn't exist. Ensure that we don't get a
    // violation for that.
    $this->assertCount(0, \Drupal::typedDataManager()->getValidator()->validate($adapter, $adapter->getConstraints()));
  }

  /**
   * Tests recursive propagation of violations.
   */
  public function testRecursiveViolationPropagation(): void {
    // We create an entity reference field with a constraint which will
    // trigger the validation of the referenced entities. Then we add a
    // required field and populate it only on the parent entity, so that
    // the child entity fails the validation.
    $definitions['field_test'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Test reference')
      ->setSetting('target_type', 'entity_test')
      ->addConstraint('TestValidatedReferenceConstraint');
    $definitions['string_required'] = BaseFieldDefinition::create('string')
      ->setLabel('Required string')
      ->setRequired(TRUE);
    $this->container->get('state')->set('entity_test.additional_base_field_definitions', $definitions);

    $this->installEntitySchema('entity_test');
    $child = EntityTest::create([
      'name' => 'test2',
      'user_id' => ['target_id' => 0],
    ]);
    $parent = EntityTest::create([
      'name' => 'test',
      'user_id' => ['target_id' => 0],
      'string_required' => 'some string',
      'field_test' => ['entity' => $child],
    ]);
    // The child entity should fail the validation and the violation should
    // propagate to the parent.
    $violations = $parent->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('field_test', $violations[0]->getPropertyPath());
    $this->assertEquals('Invalid referenced entity.', $violations[0]->getMessage());
  }

  /**
   * Tests that required map properties are validated when the map is empty.
   */
  public function testEmptyMapRequiredPropertiesValidation(): void {
    $typed_data_manager = $this->container->get('typed_data_manager');
    $definition = MapDataDefinition::create()
      ->setPropertyDefinition('foo', DataDefinition::create('string')
        ->setRequired(TRUE))
      ->setPropertyDefinition('bar', DataDefinition::create('string')
        ->setRequired(TRUE));

    // A partially populated map reports the missing required property.
    $violations = $typed_data_manager->create($definition, ['foo' => 'value'])->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('bar', $violations[0]->getPropertyPath());
    $this->assertEquals('This value should not be null.', $violations[0]->getMessage());

    // An empty map must report every missing required property as well.
    $violations = $typed_data_manager->create($definition, [])->validate();
    $this->assertCount(2, $violations);
    $this->assertEquals('foo', $violations[0]->getPropertyPath());
    $this->assertEquals('This value should not be null.', $violations[0]->getMessage());
    $this->assertEquals('bar', $violations[1]->getPropertyPath());
    $this->assertEquals('This value should not be null.', $violations[1]->getMessage());
  }

  /**
   * Tests empty map validation with an entity property.
   */
  public function testEmptyMapEntityPropertyValidation(): void {
    $definition = MapDataDefinition::create()
      ->setPropertyDefinition('entity', EntityDataDefinition::create('entity_test')
        ->setRequired(TRUE))
      ->setPropertyDefinition('label', DataDefinition::create('string'));

    $violations = $this->container->get('typed_data_manager')->create($definition, [])->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('entity', $violations[0]->getPropertyPath());
    $this->assertEquals('This value should not be null.', $violations[0]->getMessage());
  }

  /**
   * Tests that an optional map without a value is not validated.
   */
  public function testNullOptionalMapRequiredPropertiesValidation(): void {
    $definition = MapDataDefinition::create()
      ->setPropertyDefinition('foo', DataDefinition::create('string')
        ->setRequired(TRUE))
      ->setPropertyDefinition('bar', DataDefinition::create('string')
        ->setRequired(TRUE));

    $violations = $this->container->get('typed_data_manager')->create($definition, NULL)->validate();
    $this->assertCount(0, $violations);
  }

  /**
   * Data provider for ::testNestedMapValidation().
   */
  public static function nestedMapValidationProvider(): array {
    return [
      'optional child map omitted' => [
        'parent_values' => [],
        'expected_violations' => [],
      ],
      'optional child map given as empty array' => [
        'parent_values' => ['child' => []],
        'expected_violations' => ['child.foo', 'child.bar'],
      ],
    ];
  }

  /**
   * Tests validation of an optional nested map given different values.
   */
  #[DataProvider('nestedMapValidationProvider')]
  public function testNestedMapValidation(array $parent_values, array $expected_violations): void {
    $child_definition = MapDataDefinition::create()
      ->setPropertyDefinition('foo', DataDefinition::create('string')->setRequired(TRUE))
      ->setPropertyDefinition('bar', DataDefinition::create('string')->setRequired(TRUE));

    $definition = MapDataDefinition::create()
      ->setPropertyDefinition('child', $child_definition);

    $violations = $this->container->get('typed_data_manager')
      ->create($definition, $parent_values)
      ->validate();

    $this->assertCount(count($expected_violations), $violations);
    foreach ($expected_violations as $i => $path) {
      $this->assertEquals($path, $violations[$i]->getPropertyPath());
    }
  }

  /**
   * Tests that a required map given as [] validates its required properties.
   */
  public function testRequiredMapValidation(): void {
    $definition = MapDataDefinition::create()
      ->setRequired(TRUE)
      ->setPropertyDefinition('foo', DataDefinition::create('string')->setRequired(TRUE))
      ->setPropertyDefinition('bar', DataDefinition::create('string')->setRequired(TRUE));

    $violations = $this->container->get('typed_data_manager')
      ->create($definition, [])
      ->validate();

    // Three violations: one for the required root map (it is empty, so the
    // NotNull constraint fires on the map itself), and one for each required
    // child property that is missing.
    $this->assertCount(3, $violations);
    $paths = array_map(fn($violation) => $violation->getPropertyPath(), iterator_to_array($violations));
    $this->assertContains('', $paths);
    $this->assertContains('foo', $paths);
    $this->assertContains('bar', $paths);
  }

  /**
   * Tests that a partly-filled map with a missing entity property is handled.
   *
   * This is a pre-existing bug: before the fix to
   * EntityUntranslatableFieldsConstraintValidator, a partly-filled map whose
   * entity property was not given caused a fatal error.
   */
  public function testPartlyFilledMapEntityPropertyValidation(): void {
    $definition = MapDataDefinition::create()
      ->setPropertyDefinition('entity', EntityDataDefinition::create('entity_test')->setRequired(TRUE))
      ->setPropertyDefinition('label', DataDefinition::create('string'));

    $violations = $this->container->get('typed_data_manager')
      ->create($definition, ['label' => 'test'])
      ->validate();

    $this->assertCount(1, $violations);
    $this->assertEquals('entity', $violations[0]->getPropertyPath());
    $this->assertEquals('This value should not be null.', $violations[0]->getMessage());
  }

  /**
   * Tests that a map created without a value returns NULL from getValue().
   */
  public function testMapGetValueReturnsNullWhenNotSet(): void {
    $map = $this->container->get('typed_data_manager')->create(MapDataDefinition::create());
    $this->assertNull($map->getValue());
  }

  /**
   * Tests that empty field items are not validated against their properties.
   */
  public function testEmptyFieldItemValidation(): void {
    $definitions['string_multiple'] = BaseFieldDefinition::create('string')
      ->setLabel('Multiple strings')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $this->container->get('state')->set('entity_test.additional_base_field_definitions', $definitions);

    $entity = EntityTest::create([
      'name' => 'test',
      'user_id' => ['target_id' => 0],
      'string_multiple' => ['value'],
    ]);
    // The string item's "value" property is required, but an empty item must
    // not report it as missing.
    $entity->get('string_multiple')->appendItem();
    $this->assertCount(2, $entity->get('string_multiple'));
    $this->assertTrue($entity->get('string_multiple')->get(1)->isEmpty());

    $this->assertCount(0, $entity->validate());
  }

}
