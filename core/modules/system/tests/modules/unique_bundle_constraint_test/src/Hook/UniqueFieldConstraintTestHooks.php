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

    $my_bundle = 'my_bundle';
    if ($entity_type->id() === 'entity_type' && $bundle === $my_bundle) {
      $fields['name']->addConstraint('UniqueField', ['bundle' => $my_bundle]);
    }
  }

}
