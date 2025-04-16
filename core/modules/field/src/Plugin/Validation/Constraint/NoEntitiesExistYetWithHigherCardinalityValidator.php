<?php

declare(strict_types=1);

namespace Drupal\field\Plugin\Validation\Constraint;

use Drupal\Core\Config\Schema\TypeResolver;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the NoEntitiesExistYetWithHigherCardinality constraint.
 *
 * This validator checks whether existing entities of a specified type have more
 * field values than allowed by the given cardinality limit. It performs an
 * aggregate query to find the maximum delta (number of field values) for the
 * specified field across all entities of the given type, and compares it
 * against the provided cardinality.
 *
 * The validation:
 * - Skips if cardinality is unlimited (-1)
 * - Skips if the field storage configuration doesn't exist
 * - Uses EntityTypeManager to query the maximum field delta
 * - Adds a violation if the maximum delta exceeds the cardinality
 *
 * This validator implements ContainerInjectionInterface to access the entity
 * type manager service from the Drupal service container.
 *
 * @see \Drupal\field\Plugin\Validation\Constraint\NoEntitiesExistYetWithHigherCardinality
 */
class NoEntitiesExistYetWithHigherCardinalityValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $cardinality, SymfonyConstraint $constraint): void {
    assert($constraint instanceof NoEntitiesExistYetWithHigherCardinality);

    if ($cardinality === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
      return;
    }

    $object = $this->context->getObject();
    assert($object instanceof TypedDataInterface);

    $entity_type = TypeResolver::resolveExpression($constraint->entityType, $object);
    $field_name = TypeResolver::resolveExpression($constraint->fieldName, $object);

    // We cannot check this constraint if the field storage does not exist.
    $fieldStorageConfig = $this->entityTypeManager->getStorage('field_storage_config')
      ->load($entity_type . '.' . $field_name);
    if ($fieldStorageConfig === NULL) {
      return;
    }

    if ($fieldStorageConfig->hasCustomStorage()) {
      // If the field storage has custom storage, we cannot check this
      // constraint.
      return;
    }

    $max_delta_alias = 'max_delta';
    $query = $this->entityTypeManager->getStorage($entity_type)
      ->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate($field_name . '.%delta', 'MAX', NULL, $max_delta_alias);

    // When the schema for the entity does not exist the query will throw an
    // exception. This should only happen in tests.
    // @see https://www.drupal.org/node/3475719
    // @todo Remove in Drupal 12.
    try {
      $result = $query->execute();
    }
    catch (DatabaseExceptionWrapper) {
      return;
    }

    $max_delta = 0;
    if (is_array($result) && !empty($result)) {
      $max_delta = $result[0][$max_delta_alias] ?? 0;
    }

    if ($max_delta > $cardinality) {
      $this->context->addViolation($constraint->message, [
        '@entity_type' => $entity_type,
        '@field_name' => $field_name,
        '@max_delta' => $max_delta,
        '@cardinality' => $cardinality,
      ]);
    }
  }

}
