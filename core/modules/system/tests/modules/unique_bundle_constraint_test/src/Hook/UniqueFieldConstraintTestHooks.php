<?php

declare(strict_types=1);

namespace Drupal\unique_bundle_constraint_test\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for unique_field_constraint_test.
 */
class UniqueFieldConstraintTestHooks {

  /**
  * Prevent duplicates in my_bundle.
  */
  #[Hook('entity_bundle_field_info_alter')]
  public function uniqueBundleField(&$fields, EntityTypeInterface $entity_type, $bundle): void {
    $my_bundle = 'one';
    $my_field = 'custom_id';
    if ($entity_type->id() === 'entity_test_with_bundle' && $bundle === $my_bundle && isset($fields[$my_field])) {
      $fields[$my_field]->addConstraint('UniqueField', ['bundle' => $my_bundle]);
    }
  }

}
