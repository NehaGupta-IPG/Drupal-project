<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraints\Sequentially;

/**
 * AfterInstallConstraint constraint.
 *
 * Extends the Symfony All constraint to only validate after install.
 */
#[Constraint(
  id: 'AfterInstall',
  label: new TranslatableMarkup('After install composite constraint', [], ['context' => 'Validation']),
  type: FALSE
)]
class AfterInstallConstraint extends Sequentially {

  /**
   * {@inheritdoc}
   */
  public function __construct(mixed $constraints = NULL, ?array $groups = NULL, mixed $payload = NULL) {
    $constraint_manager = \Drupal::service('validation.constraint');

    // Instantiate constraint objects for array definitions.
    $constraint_objects = [];
    foreach ($constraints as $id => $options) {
      if (!is_object($options)) {
        $constraint_objects[] = $constraint_manager->create($id, $options);
      }
      else {
        $constraint_objects[] = $options;
      }
    }
    parent::__construct($constraint_objects, $groups, $payload);
  }

}
