<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Validation;

use Drupal\Core\Validation\Plugin\Validation\Constraint\UniqueFieldValueValidator;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestBundle;
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
  ];

  /**
   * Tests validating bundle specific constraint.
   *
   * @legacy-covers ::validate
   */
  public function testBundleConstraint(): void {
    $this->installEntitySchema('entity_test');
    $bundle = EntityTestBundle::create([
      'id' => 'my_bundle',
    ]);
    $bundle->save();

    // Create and save an entity with a given field value in the field that has
    // the unique constraint in this bundle.
    EntityTest::create([
      'name' => 'A totally unique entity name',
      'type' => 'my_bundle',
    ])->save();

    // Prepare a second entity with the same value in the unique field.
    $entity = EntityTest::create([
      'name' => 'A totally unique entity name',
      'type' => 'my_bundle',
    ]);
    /** @var \Symfony\Component\Validator\ConstraintViolationList $violations */
    $violations = $entity->get('name')->validate();

    $message = 'A ' . $entity->getEntityType()->getSingularLabel() . ' with Name A totally unique entity name already exists.';

    // Check that the validation has created the appropriate violation.
    $this->assertCount(1, $violations);
    $this->assertEquals($message, $violations[0]->getMessage());

    $bundle = EntityTestBundle::create([
      'id' => 'another_bundle',
    ]);
    $bundle->save();
    // Create and save an entity with a given field value in the field that has
    // the unique constraint in this bundle.
    EntityTest::create([
      'name' => 'A totally unique entity name',
      'type' => 'another_bundle',
    ])->save();

    // Prepare a second entity with the same value in the unique field.
    $entity = EntityTest::create([
      'name' => 'A totally unique entity name',
      'type' => 'another_bundle',
    ]);
    /** @var \Symfony\Component\Validator\ConstraintViolationList $violations */
    $violations = $entity->get('name')->validate();
    // Check that there are no violations for other bundles.
    $this->assertCount(0, $violations);
  }

}
