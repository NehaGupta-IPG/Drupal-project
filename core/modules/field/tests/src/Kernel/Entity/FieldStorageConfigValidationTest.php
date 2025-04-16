<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Kernel\Entity;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Config\ConfigEntityValidationTestBase;

/**
 * Tests validation of field_storage_config entities.
 *
 * @group field
 */
class FieldStorageConfigValidationTest extends ConfigEntityValidationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field', 'entity_test', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_mul_with_bundle');

    $this->entity = FieldStorageConfig::create([
      'type' => 'boolean',
      'field_name' => 'test',
      'entity_type' => 'entity_test_mul_with_bundle',
      'custom_storage' => FALSE,
    ]);
    $this->entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function testImmutableProperties(array $valid_values = [], ?array $additional_expected_validation_errors_when_modified = NULL): void {
    parent::testImmutableProperties($valid_values + [
      'entity_type' => 'entity_test_with_bundle',
      'type' => 'email',
    ], $additional_expected_validation_errors_when_modified);
  }

  /**
   * Tests that the field type plugin is validated.
   */
  public function testFieldTypePlugin(): void {
    $this->entity->set('type', 'non_existent');
    $this->assertValidationErrors([
      '' => "The 'type' property cannot be changed.",
      'type' => "The 'non_existent' plugin does not exist.",
    ]);
  }

  /**
   * Tests that the target entity type is validated.
   */
  public function testEntityType(): void {
    // Ensure the target entity type is valid to begin with.
    $this->assertValidationErrors([]);

    $this->entity->set('entity_type', 'strange_entity');
    $this->assertValidationErrors([
      '' => "The 'entity_type' property cannot be changed.",
      'entity_type' => "The 'strange_entity' plugin does not exist.",
    ]);

    // A valid, but non-fieldable, entity type should raise an error.
    $this->entity->set('entity_type', 'field_config');
    $this->assertValidationErrors([
      '' => "The 'entity_type' property cannot be changed.",
      'entity_type' => "The 'field_config' plugin must implement or extend \Drupal\Core\Entity\FieldableEntityInterface.",
    ]);
  }

  /**
   * Tests that the field type plugin's existence is validated.
   */
  public function testFieldTypePluginIsValidated(): void {
    // The `type` property is immutable, so we need to clone the entity in
    // order to cleanly change its immutable properties.
    $this->entity = $this->entity->createDuplicate()
      ->set('type', 'invalid');

    $this->assertValidationErrors([
      'type' => "The 'invalid' plugin does not exist.",
    ]);
  }

}
