<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates complex data.
 */
class EntityTypeExistsConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    assert($constraint instanceof EntityTypeExistsConstraint);

    if (!is_string($value) || $value === '') {
      $this->context->addViolation($constraint->valueIsNoStringMessage);
      return;
    }

    $entity_types = \Drupal::entityTypeManager()->getDefinitions();

    if (!array_key_exists($value, $entity_types)) {
      $this->context->addViolation($constraint->message, [
        '@entity_type_id' => $value,
      ]);
    }
    if (!empty($constraint->requiredInterface)) {
      $entity_type = $entity_types[$value];
      if (!$entity_type->entityClassImplements($constraint->requiredInterface)) {
        $this->context->addViolation($constraint->interfaceMissingMessage, [
          '@entity_type_id' => $value,
          '@interface' => $constraint->requiredInterface,
        ]);
      }
    }
  }

}
