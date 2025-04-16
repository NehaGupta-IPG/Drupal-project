<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Kernel\Entity;

use Drupal\entity_test\Entity\EntityTestMulBundle;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Tests validation of field_config entities.
 *
 * @group field
 */
class FieldConfigValidationTest extends FieldStorageConfigValidationTest {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field', 'entity_test', 'user', 'node', 'text'];

  /**
   * {@inheritdoc}
   */
  protected static array $propertiesWithOptionalValues = [
    'default_value',
    'default_value_callback',
    'description',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installConfig('node');

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_with_bundle');

    $this->createContentType(['type' => 'one']);
    $this->createContentType(['type' => 'another']);

    EntityTestBundle::create(['id' => 'one'])->save();
    EntityTestMulBundle::create(['id' => 'one'])->save();

    // Specifically create a bundle for `entity_test_bundle` and
    // `entity_test_mul_with_bundle` content entities confusingly named
    // `another`, to allow testing the modifying of the `entity_type` field on
    // FieldConfig entities without triggering additional validation errors.
    // @see ::providerImmutableFields()
    EntityTestBundle::create(['id' => 'another'])->save();
    EntityTestMulBundle::create([
      'id' => 'another',
      'label' => $this->randomString(),
    ])->save();

    // The field storage was created in the parent method.
    $field_storage = $this->entity;

    $this->entity = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'one',
      'settings' => [
        'on_label' => 'Hello!',
        'off_label' => 'Goodbye.',
      ],
    ]);
    $this->entity->save();
  }

  /**
   * Tests that validation fails if config dependencies are invalid.
   */
  public function testInvalidDependencies(): void {
    // Remove the config dependencies from the field entity.
    $dependencies = $this->entity->getDependencies();
    $dependencies['config'] = [];
    $this->entity->set('dependencies', $dependencies);

    $this->assertValidationErrors(['' => 'This field requires a field storage.']);

    // Things look sort-of like `field.storage.*.*` should fail validation
    // because they don't exist.
    $dependencies['config'] = [
      'field.storage.fake',
      'field.storage.',
      'field.storage.user.',
    ];
    $this->entity->set('dependencies', $dependencies);
    $this->assertValidationErrors([
      'dependencies.config.0' => "The 'field.storage.fake' config does not exist.",
      'dependencies.config.1' => "The 'field.storage.' config does not exist.",
      'dependencies.config.2' => "The 'field.storage.user.' config does not exist.",
    ]);
  }

  /**
   * Tests that the field type plugin is validated.
   */
  public function testFieldTypePlugin(): void {
    $this->entity->set('field_type', 'non_existent');
    // If we don't clear the previous settings here, we will get unrelated
    // validation errors (in addition to the one we're expecting), because the
    // settings from the *old* field_type won't match the config schema for the
    // settings of the *new* field_type.
    $this->entity->set('settings', []);
    $this->assertValidationErrors([
      '' => "The 'field_type' property cannot be changed.",
      'field_type' => [
        "The 'non_existent' plugin does not exist.",
        "Expected this to match the value in the 'field.storage.entity_test_mul_with_bundle.test' config at the 'type' property: 'boolean' was expected, not 'non_existent'.",
      ],
    ]);
  }

  /**
   * Tests that the target entity type is validated.
   *
   * 90% identical to the parent: an additional validation error is triggered
   * due to bundle validation.
   */
  public function testEntityType(): void {
    // Ensure the target entity type is valid to begin with.
    $this->assertValidationErrors([]);

    // Ensure that it is at least plausible that the `entity_type` is modified:
    // a corresponding FieldStorageConfig must exist due to the entity-level
    // `RequiredConfigDependencies` constraint.
    FieldStorageConfig::create([
      'type' => 'boolean',
      'field_name' => 'test',
      'entity_type' => 'entity_test',
    ])->save();
    $this->entity->set('entity_type', 'entity_test');
    $this->assertValidationErrors([
      '' => "The 'entity_type' property cannot be changed.",
      'bundle' => "The 'one' bundle does not exist on the 'entity_test' entity type.",
    ]);
  }

  /**
   * Tests that the bundle is validated.
   */
  public function testBundle(): void {
    $entity_type_id = 'entity_test_mul_with_bundle';
    $field_name = 'test';

    // Assert that the FieldStorageConfig which this FieldConfig will depend on,
    // already exists.
    $field_storage_config = FieldStorageConfig::loadByName($entity_type_id, $field_name);
    $this->assertInstanceOf(FieldStorageConfigInterface::class, $field_storage_config);

    // Try to create an instance of this field on a bundle that does not exist.
    $this->entity = FieldConfig::create([
      'bundle' => 'non_existent',
      'field_storage' => $field_storage_config,
    ]);
    // The field storage is not listed in the entity's config dependencies,
    // because the dependencies have not been recalculated yet. They can't be
    // recalculated until the target bundle exists, or we'll get an exception.
    // So, for testing purposes, use reflection to access the protected
    // addDependency() method and add the field storage as a dependency.
    // @see \Drupal\Core\Field\FieldConfigBase::calculateDependencies()
    (new \ReflectionMethod($this->entity, 'addDependency'))
      ->invoke($this->entity, 'config', $field_storage_config->getConfigDependencyName());
    $this->assertValidationErrors([
      'bundle' => "The 'non_existent' bundle does not exist on the 'entity_test_mul_with_bundle' entity type.",
    ]);

    // Next, try to create it on a bundle that *does* exist. We need to
    // recalculate dependencies because that's how we check whether or not the
    // bundle exists.
    $this->entity->set('bundle', 'one')->calculateDependencies();
    $this->assertValidationErrors([]);
  }

  /**
   * Tests validation of a field_config's default value.
   */
  public function testMultilineTextFieldDefaultValue(): void {
    $this->installEntitySchema('user');
    // First, create a field storage for which a complex default value exists.
    $this->enableModules(['text', 'user']);
    $text_field_storage_config = FieldStorageConfig::create([
      'type' => 'text_with_summary',
      'field_name' => 'novel',
      'entity_type' => 'user',
    ]);
    $text_field_storage_config->save();

    $this->entity = FieldConfig::create([
      'field_storage' => $text_field_storage_config,
      'bundle' => 'user',
      'default_value' => [
        0 => [
          'value' => "Multi\nLine",
          'summary' => '',
          'format' => 'basic_html',
        ],
      ],
      'dependencies' => [
        'config' => [
          $text_field_storage_config->getConfigDependencyName(),
        ],
      ],
    ]);
    $this->assertValidationErrors([]);
  }

  /**
   * Tests that the target bundle of the field is checked.
   */
  public function testTargetBundleMustExist(): void {
    $this->entity->set('bundle', 'nope');
    $this->assertValidationErrors([
      '' => "The 'bundle' property cannot be changed.",
      'bundle' => "The 'nope' bundle does not exist on the 'entity_test_mul_with_bundle' entity type.",
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function testImmutableProperties(array $valid_values = [], ?array $additional_expected_validation_errors_when_modified = NULL): void {
    // Ensure that it is at least plausible that the `entity_type` is modified:
    // a corresponding FieldStorageConfig must exist due to the entity-level
    // `RequiredConfigDependencies` constraint.
    FieldStorageConfig::create([
      'type' => 'boolean',
      'field_name' => 'test',
      'entity_type' => 'entity_test_with_bundle',
    ])->save();
    // Same thing for `field_name`.
    FieldStorageConfig::create([
      'type' => 'boolean',
      'field_name' => 'foobar',
      'entity_type' => 'entity_test_mul_with_bundle',
    ])->save();

    parent::testImmutableProperties([
      'bundle' => 'another',
      'field_type' => 'email',
      'field_name' => 'foobar',
    ], [
      'field_type' => [
        'field_type' => "Expected this to match the value in the 'field.storage.entity_test_mul_with_bundle.test' config at the 'type' property: 'boolean' was expected, not 'email'.",
        'settings' => [
          "'on_label' is an unknown key because field_type is email (see config schema type field.field_settings.email).",
          "'off_label' is an unknown key because field_type is email (see config schema type field.field_settings.email).",
        ],
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function testRequiredPropertyKeysMissing(?array $additional_expected_validation_errors_when_missing = NULL): void {
    parent::testRequiredPropertyKeysMissing([
      'dependencies' => [
        // @see ::testInvalidDependencies()
        // @see \Drupal\Core\Config\Plugin\Validation\Constraint\RequiredConfigDependenciesConstraintValidator
        '' => 'This field requires a field storage.',
      ],
      // If the settings are removed, we should see errors about them missing.
      'settings' => [
        'settings' => [
          "'on_label' is a required key because field_type is boolean (see config schema type field.field_settings.boolean).",
          "'off_label' is a required key because field_type is boolean (see config schema type field.field_settings.boolean).",
        ],
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function testRequiredPropertyValuesMissing(?array $additional_expected_validation_errors_when_missing = NULL): void {
    parent::testRequiredPropertyValuesMissing([
      'dependencies' => [
        // @see ::testInvalidDependencies()
        // @see \Drupal\Core\Config\Plugin\Validation\Constraint\RequiredConfigDependenciesConstraintValidator
        '' => 'This field requires a field storage.',
      ],
    ]);
  }

  /**
   * Tests that the field type plugin's existence is validated.
   */
  public function testFieldTypePluginIsValidated(): void {
    // The `field_type` property is immutable, so we need to clone the entity in
    // order to cleanly change its immutable properties.
    $this->entity = $this->entity->createDuplicate()
      // We need to clear the current settings, or we will get validation errors
      // because the old settings are not supported by the new field type.
      ->set('settings', [])
      ->set('field_type', 'invalid');

    $this->assertValidationErrors([
      'field_type' => [
        "The 'invalid' plugin does not exist.",
        "Expected this to match the value in the 'field.storage.entity_test_mul_with_bundle.test' config at the 'type' property: 'boolean' was expected, not 'invalid'.",
      ],
    ]);
  }

  /**
   * Tests that entity reference selection handler plugin IDs are validated.
   */
  public function testEntityReferenceSelectionHandlerIsValidated(): void {
    $this->container->get('state')
      ->set('field_test_disable_broken_entity_reference_handler', TRUE);
    $this->enableModules(['field_test']);

    // The `field_type` property is immutable, so we need to clone the entity in
    // order to cleanly change its immutable properties.
    $this->entity = $this->entity->createDuplicate()
      ->set('field_type', 'entity_reference')
      ->set('settings', ['handler' => 'non_existent']);

    $this->assertValidationErrors([
      'field_type' => "Expected this to match the value in the 'field.storage.entity_test_mul_with_bundle.test' config at the 'type' property: 'boolean' was expected, not 'entity_reference'.",
      'settings.handler' => "The 'non_existent' plugin does not exist.",
    ]);
  }

}
