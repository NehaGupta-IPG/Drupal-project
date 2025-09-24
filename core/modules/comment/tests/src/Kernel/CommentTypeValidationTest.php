<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Kernel;

use Drupal\comment\Entity\CommentType;
use Drupal\KernelTests\Core\Config\ConfigEntityValidationTestBase;

/**
 * Tests validation of comment_type entities.
 *
 * @group comment
 */
class CommentTypeValidationTest extends ConfigEntityValidationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['comment', 'node', 'user'];

  /**
   * The config entity properties whose values are optional (set to NULL).
   *
   * @var string[]
   * @see \Drupal\Core\Config\Entity\ConfigEntityTypeInterface::getPropertiesToExport()
   * @see ::testRequiredPropertyValuesMissing()
   */
  protected static array $propertiesWithOptionalValues = [
    '_core',
    'third_party_settings',
    'description',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entity = CommentType::create([
      'id' => 'test',
      'label' => 'Test',
      'target_entity_type_id' => 'node',
    ]);
    $this->entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function testRequiredPropertyValuesMissing(?array $additional_expected_validation_errors_when_missing = NULL): void {
    parent::testRequiredPropertyValuesMissing([
      'target_entity_type_id' => [
        'target_entity_type_id' => [
          'This value should not be null.',
          'The value is not a string, cannot validate if entity type exists.',
          'The value is not a string, cannot validate if entity is commentable.',
        ],
      ],
    ]);
  }

}
