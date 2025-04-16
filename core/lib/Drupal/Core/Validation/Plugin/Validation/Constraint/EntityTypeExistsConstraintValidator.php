<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates complex data.
 */
class EntityTypeExistsConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a RequiredConfigDependenciesConstraintValidator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    assert($constraint instanceof EntityTypeExistsConstraint);

    if (!is_string($value) || $value === '') {
      $this->context->addViolation($constraint->valueIsNoStringMessage);
      return;
    }

    $entity_types = $this->entityTypeManager->getDefinitions();

    if (!array_key_exists($value, $entity_types)) {
      $this->context->addViolation($constraint->message, [
        '@entity_type_id' => $value,
      ]);
    }
    if (!empty($constraint->interface)) {
      $entity_type = $entity_types[$value];
      if (!$entity_type->entityClassImplements($constraint->interface)) {
        $this->context->addViolation($constraint->interfaceMissingMessage, [
          '@entity_type_id' => $value,
          '@interface' => $constraint->interface,
        ]);
      }
    }
  }

}
