<?php

declare(strict_types=1);

namespace Drupal\Tests\options\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the caching behavior for options fields.
 */
#[Group('options')]
#[RunTestsInSeparateProcesses]
class OptionsFieldCacheTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['options'];

  /**
   * The field name used in the test.
   */
  protected string $fieldName = 'test_options';

  /**
   * The field storage definition used to create the field storage.
   */
  protected array $fieldStorageDefinition;

  /**
   * The list field storage used in the test.
   */
  protected FieldStorageConfigInterface $fieldStorage;

  /**
   * The list field in the bundle 1.
   */
  protected FieldConfigInterface $bundle1Field;

  /**
   * The list field in the bundle 2.
   */
  protected FieldConfigInterface $bundle2Field;

  /**
   * The entity for the bundle 1.
   */
  protected EntityInterface $bundle1Entity;

  /**
   * The entity for the bundle 2.
   */
  protected EntityInterface $bundle2Entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_with_bundle');

    // Create two bundles of the entity type.
    $bundle1 = EntityTestBundle::create([
      'id' => 'bundle1',
      'label' => 'Test Bundle 1',
    ]);
    $bundle1->save();
    $bundle2 = EntityTestBundle::create([
      'id' => 'bundle2',
      'label' => 'Test Bundle 2',
    ]);
    $bundle2->save();

    // Create a field storage that will be shared by both fields.
    $this->fieldStorageDefinition = [
      'field_name' => $this->fieldName,
      'entity_type' => 'entity_test_with_bundle',
      'type' => 'list_string',
      'cardinality' => 1,
      'settings' => [
        'allowed_values' => [
          'value1' => 'Value 1',
          'value2' => 'Value 2',
        ],
        'allowed_values_function' => __CLASS__ . '::allowedValuesFunction',
      ],
    ];
    $this->fieldStorage = FieldStorageConfig::create($this->fieldStorageDefinition);
    $this->fieldStorage->save();

    // Create two field instances, one for each bundle.
    $this->bundle1Field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => 'bundle1',
    ]);
    $this->bundle1Field->save();
    $this->bundle2Field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => 'bundle2',
    ]);
    $this->bundle2Field->save();

    // Create two entities, one for each bundle.
    $this->bundle1Entity = EntityTestWithBundle::create([
      'type' => 'bundle1',
      'name' => 'Test entity bundle1',
    ]);
    $this->bundle1Entity->save();
    $this->bundle2Entity = EntityTestWithBundle::create([
      'type' => 'bundle2',
      'name' => 'Test entity bundle2',
    ]);
    $this->bundle2Entity->save();

  }

  /**
   * Tests that the allowed values cache is correctly set per bundle.
   */
  public function testOptionsAllowedValuesIsCachedPerBundle(): void {
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = \Drupal::service('entity_field.manager');

    // Load the field definitions for each bundle.
    $bundle1FieldDefinition = $entityFieldManager
      ->getFieldDefinitions('entity_test_with_bundle', 'bundle1')[$this->fieldName];
    $bundle2FieldDefinition = $entityFieldManager
      ->getFieldDefinitions('entity_test_with_bundle', 'bundle2')[$this->fieldName];

    // Get the allowed values for each bundle.
    $bundle1AllowedValues = options_allowed_values(
      $bundle1FieldDefinition->getFieldStorageDefinition(),
      $this->bundle1Entity
    );
    $bundle2AllowedValues = options_allowed_values(
      $bundle2FieldDefinition->getFieldStorageDefinition(),
      $this->bundle2Entity
    );

    // Check that the allowed values are correct for each bundle.
    $this->assertEquals(['value1' => 'Value 1'], $bundle1AllowedValues);
    $this->assertEquals(['value2' => 'Value 2'], $bundle2AllowedValues);
  }

  /**
   * Determines the allowed values for a field based on the specified entity bundle.
   *
   * This callback provides a subset of allowed values tailored for the specific
   * entity bundles `bundle1` and `bundle2`.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $definition
   *   The field storage definition.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity instance for which the allowed values are being evaluated.
   * @param bool &$cacheable
   *   A flag passed by reference that indicates whether the allowed values are cacheable.
   *
   * @return array
   *   An associative array of allowed values, where the keys represent the stored
   *   values and the values are their human-readable labels.
   */
  public static function allowedValuesFunction(FieldStorageDefinitionInterface $definition, EntityInterface $entity, bool &$cacheable): array {
    return match ($entity->bundle()) {
      'bundle1' => [
        'value1' => 'Value 1',
      ],
      'bundle2' => [
        'value2' => 'Value 2',
      ],
      default => $definition->getSetting('allowed_values'),
    };
  }

}
