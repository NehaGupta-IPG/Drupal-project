<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates that a field is unique for the given config entity type.
 */
class UniqueFieldCombinationConstraintValidator extends ConstraintValidator {

  use TreeAwareConstraintTrait;

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    assert($constraint instanceof UniqueFieldCombinationConstraint);

    if (!is_array($value)) {
      throw new UnexpectedTypeException($value, 'array');
    }

    // Find the parent mapping.
    $mapping = $this->getParentProperty();

    // Verify the required fields are present; if not, that's a logical error in
    // the config schema, not in concrete config.
    $properties = $mapping->getProperties();
    $missing_fields = array_diff($constraint->fields, array_keys($properties));
    if (!empty($missing_fields)) {
      throw new \LogicException(sprintf('This validation constraint is configured to inspect the fields %s, but some do not exist: %s.',
        implode(', ', $constraint->fields),
        implode(', ', $missing_fields)
      ));
    }

    // Construct an entity query.
    // @see \Drupal\Core\Config\ConfigImporter::checkOp()
    // @see \Drupal\Core\Config\ConfigInstaller::createConfiguration()
    $config_manager = \Drupal::service('config.manager');
    $entity_type_id = $config_manager->getEntityTypeIdByName($mapping->getRoot()->getName());
    $entity_storage = $config_manager->getEntityTypeManager()->getStorage($entity_type_id);
    $query = $entity_storage->getQuery()
      ->accessCheck(FALSE);
    foreach ($constraint->fields as $field_name) {
      $query->condition($field_name, $mapping->get($field_name)->getValue());
    }

    $uuid = $mapping->get('uuid')->getValue();
    if (!empty($uuid)) {
      $query->condition('uuid', $uuid, '<>');
    }

    $value_taken = (bool) $query
      ->range(0, 1)
      ->execute();

    if ($value_taken) {
      $this->context->addViolation($constraint->message, [
        '@entity_type' => $entity_type_id,
        '@field_name_list' => implode(', ', $constraint->fields),
        '%field_value_list' => implode(', ', array_map(fn (string $field_name) => $mapping->get($field_name)->getValue(), $constraint->fields)),
      ]);
    }
  }

}
