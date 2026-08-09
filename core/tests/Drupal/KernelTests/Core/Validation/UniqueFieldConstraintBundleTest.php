<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Validation;

use Drupal\Core\Validation\Plugin\Validation\Constraint\UniqueFieldValueValidator;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the unique field value validation constraint within bundle.
 */
#[CoversClass(UniqueFieldValueValidator::class)]
#[Group('Validation')]
#[RunTestsInSeparateProcesses]
class UniqueFieldConstraintBundleTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'unique_bundle_constraint_test',
    'field',
  ];

  /**
   * Field name used for tests.
   *
   * @var string
   */
  protected string $fieldName;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $entity_type = 'entity_test_with_bundle';
    $this->fieldName = 'custom_id';

    $this->installEntitySchema($entity_type);

    EntityTestBundle::create(['id' => 'one'])->save();
    EntityTestBundle::create(['id' => 'another'])->save();

    FieldStorageConfig::create([
      'type' => 'string',
      'field_name' => $this->fieldName,
      'entity_type' => $entity_type,
    ])->save();
    $field_storage = FieldStorageConfig::loadByName($entity_type, $this->fieldName);

    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'one',
    ])->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'another',
    ])->save();
  }

  /**
   * Tests validating bundle specific constraint.
   */
  public function testBundleConstraint(): void {
    // Create and save an entity with a given field value in the field that has
    // the unique constraint in this bundle.
    EntityTestWithBundle::create([
      'name' => 'A totally unique entity name',
      'type' => 'one',
      $this->fieldName => 'unique custom ID',
    ])->save();

    // Prepare a second entity with the same value in the unique field.
    $entity = EntityTestWithBundle::create([
      'name' => 'A totally unique entity name',
      'type' => 'one',
      $this->fieldName => 'unique custom ID',
    ]);
    /** @var \Symfony\Component\Validator\ConstraintViolationList $violations */
    $violations = $entity->get($this->fieldName)->validate();

    $message = 'A ' . $entity->getEntityType()->getSingularLabel() . ' with custom_id unique custom ID already exists.';

    // Check that the validation has created the appropriate violation.
    $this->assertCount(1, $violations);
    $this->assertEquals($message, $violations[0]->getMessage());

    // Create and save an entity with a given field value in the field that has
    // the unique constraint in this bundle.
    EntityTestWithBundle::create([
      'name' => 'A totally unique entity name',
      'type' => 'another',
      $this->fieldName => 'unique custom ID',
    ])->save();

    // Prepare a second entity with the same value in the unique field.
    $entity = EntityTestWithBundle::create([
      'name' => 'A totally unique entity name',
      'type' => 'another',
      $this->fieldName => 'unique custom ID',
    ]);
    /** @var \Symfony\Component\Validator\ConstraintViolationList $violations */
    $violations = $entity->get($this->fieldName)->validate();
    // Check that there are no violations for other bundles.
    $this->assertCount(0, $violations);
  }

}
