<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Validation\Plugin\Validation\Constraint\FullyValidatableConstraint;

/**
 * Resource test base class for config entities.
 *
 * @todo Remove this in https://www.drupal.org/node/2300677.
 */
abstract class ConfigEntityResourceTestBase extends ResourceTestBase {

  /**
   * A list of test methods to skip.
   *
   * @var array
   */
  const SKIP_METHODS = [
    'testRelated',
    'testRelationships',
    'testDeleteIndividual',
    'testRevisions',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    if (in_array($this->name(), static::SKIP_METHODS, TRUE)) {
      // Skip before installing Drupal to prevent unnecessary use of resources.
      $this->markTestSkipped("Not yet supported for config entities.");
    }
    parent::setUp();
  }

  /**
   * Whether the tested config entity type is fully validatable.
   *
   * @return bool
   *   Whether the tested config entity type is fully validatable.
   *
   * @see \Drupal\KernelTests\Core\Config\ConfigEntityValidationTestBase::isFullyValidatable()
   */
  protected function isFullyValidatable(): bool {
    $typed_config = $this->container->get('config.typed');
    assert($typed_config instanceof TypedConfigManagerInterface);
    // @see \Drupal\Core\Entity\Plugin\DataType\ConfigEntityAdapter::getConfigTypedData()
    $config_entity_type_schema_constraints = $typed_config
      ->createFromNameAndData(
        $this->entity->getConfigDependencyName(),
        $this->entity->toArray()
      )->getConstraints();

    foreach ($config_entity_type_schema_constraints as $constraint) {
      if ($constraint instanceof FullyValidatableConstraint) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
