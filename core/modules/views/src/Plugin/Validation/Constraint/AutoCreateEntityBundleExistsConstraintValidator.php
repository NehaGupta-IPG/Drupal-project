<?php

declare(strict_types = 1);

namespace Drupal\views\Plugin\Validation\Constraint;

use Drupal\Core\Config\Schema\TypeResolver;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\FieldStorageConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use function array_key_exists;
use function assert;
use function is_string;


/**
 * Validates that the bundle exists of an entity reference field with auto creation option enabled.
 */
class AutoCreateEntityBundleExistsConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(private readonly EntityTypeBundleInfoInterface $bundleInfo, private readonly EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(EntityTypeBundleInfoInterface::class),
      $container->get(EntityTypeManagerInterface::class)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    assert($constraint instanceof AutoCreateEntityBundleExistsConstraint);
    if (!is_string($value)) {
      throw new UnexpectedTypeException($value, 'string');
    }
    // The host entity which has entity reference field with views selection and auto creation.
    $entity_type_id = TypeResolver::resolveDynamicTypeName("[$constraint->entityTypeId]", $this->context->getObject());
    $field_name = TypeResolver::resolveDynamicTypeName("[$constraint->fieldName]", $this->context->getObject());

    /** @var \Drupal\field\FieldStorageConfigInterface|null $fieldStorageConfig */
    $fieldStorageConfig = $this->entityTypeManager->getStorage('field_storage_config')->load($entity_type_id . '.' . $field_name);
    assert($fieldStorageConfig instanceof FieldStorageConfigInterface);
    $target_entity_type_id = $fieldStorageConfig->getSetting('target_type');

    // The auto_create_bundle needs to be a bundle of the target entity type.
    if (!array_key_exists($value, $this->bundleInfo->getBundleInfo($target_entity_type_id))) {
      $this->context->addViolation($constraint->message, [
        '@bundle' => $value,
        '@entity_type_id' => $target_entity_type_id,
      ]);
    }
  }

}
