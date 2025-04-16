<?php

namespace Drupal\Core\Field\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the IsBaseField constraint.
 */
class IsBaseFieldConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs an IsBaseFieldConstraintValidator object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   */
  public function __construct(protected readonly EntityFieldManagerInterface $entityFieldManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(EntityFieldManagerInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof IsBaseFieldConstraint);

    if (!$value instanceof FieldDefinitionInterface) {
      throw new UnexpectedTypeException($value, FieldDefinitionInterface::class);
    }
    $field_name = $value->getName();
    $entity_type_id = $value->getTargetEntityTypeId();
    if (!array_key_exists($field_name, $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id))) {
      $this->context->addViolation($constraint->message, [
        '@field_name' => $field_name,
        '@entity_type' => $entity_type_id,
      ]);
    }
  }

}
