<?php

namespace Drupal\jsonapi\Entity;

use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\Config\Schema\Undefined;
use Drupal\Core\Entity\Plugin\Validation\Constraint\ImmutablePropertiesConstraint;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\TypedConfigManager;
use Drupal\Core\Entity\EntityConstraintViolationList;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\Core\Validation\Plugin\Validation\Constraint\FullyValidatableConstraint;
use Drupal\jsonapi\Exception\UnprocessableHttpEntityException;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Provides a method to validate an entity.
 *
 * @internal JSON:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/drupal/issues/3032787
 * @see jsonapi.api.php
 */
trait EntityValidationTrait {

  /**
   * Verifies that an entity does not violate any validation constraints.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   * @param string[] $field_names
   *   (optional) An array of field names. If specified, filters the violations
   *   list to include only this set of fields. Defaults to NULL,
   *   which means that all violations will be reported.
   *
   * @throws \Drupal\jsonapi\Exception\UnprocessableHttpEntityException
   *   Thrown when violations remain after filtering.
   *
   * @see \Drupal\rest\Plugin\rest\resource\EntityResourceValidationTrait::validate()
   */
  protected static function validate(EntityInterface $entity, ?array $field_names = NULL): void {
    if (!$entity instanceof FieldableEntityInterface && !$entity instanceof ConfigEntityInterface) {
      return;
    }

    if ($entity instanceof ConfigEntityInterface) {
      $typed_config = \Drupal::service('config.typed')->createFromNameAndData($entity->getConfigDependencyName(), $entity->toArray());
      // Reject identity changes before plugin validators resolve dependencies.
      $identity_constraints = array_filter($entity->getTypedData()->getConstraints(), static fn ($constraint) => $constraint instanceof ImmutablePropertiesConstraint);
      $validator = \Drupal::service('validation.basic_recursive_validator_factory')->createValidator();
      $violations = new EntityConstraintViolationList($entity, $validator->validate($entity, $identity_constraints));
      if (count($violations) === 0) {
        static::validateConfigSchema($typed_config, $violations);
      }
      if (count($violations) === 0) {
        $violations = $entity->getTypedData()->validate();
      }
    }
    else {
      $violations = $entity->validate();
      // Inaccessible fields cannot have been changed by this request.
      $violations->filterByFieldAccess();
      if ($field_names !== NULL) {
        $violations->filterByFields(
          array_diff(array_keys($entity->getFieldDefinitions()), $field_names)
        );
      }
    }

    if (count($violations) > 0) {
      // Semantic errors in a well-formed request use 422 rather than 400.
      $exception = new UnprocessableHttpEntityException();
      $exception->setViolations($violations);
      throw $exception;
    }
  }

  /**
   * Checks every resolved schema root, including nested plugin settings.
   */
  private static function validateConfigSchema(TraversableTypedDataInterface $data, EntityConstraintViolationList $violations): void {
    if (TypedConfigManager::getStaticTypeRoot($data) === $data) {
      $validatable = FALSE;
      foreach ($data->getConstraints() as $constraint) {
        if ($constraint instanceof FullyValidatableConstraint) {
          $validatable = TRUE;
          break;
        }
      }
      if (!$validatable) {
        $path = $data === $data->getRoot() ? '' : substr($data->getPropertyPath(), strlen($data->getRoot()->getPropertyPath()) + 1);
        $type = $data->getDataDefinition()->getDataType();
        $violations->add(new ConstraintViolation("The value at property path $path cannot be validated because the resolved schema type \"$type\" is not fully validatable.", NULL, [], $data->getRoot(), $path, $data->getValue()));
        return;
      }
    }

    if ($data->getValue() !== NULL && !is_array($data->getValue())) {
      $path = substr($data->getPropertyPath(), strlen($data->getRoot()->getPropertyPath()) + 1);
      $violations->add(new ConstraintViolation("The value at property path $path must be a mapping or sequence.", NULL, [], $data->getRoot(), $path, $data->getValue()));
      return;
    }
    foreach ($data as $child) {
      if ($child instanceof Undefined) {
        $path = substr($child->getPropertyPath(), strlen($data->getRoot()->getPropertyPath()) + 1);
        $violations->add(new ConstraintViolation("The value at property path $path has no defined schema.", NULL, [], $data->getRoot(), $path, $child->getValue()));
      }
      elseif ($child instanceof PrimitiveInterface && is_array($child->getValue())) {
        $path = substr($child->getPropertyPath(), strlen($data->getRoot()->getPropertyPath()) + 1);
        $violations->add(new ConstraintViolation("The value at property path $path must be a scalar.", NULL, [], $data->getRoot(), $path, $child->getValue()));
      }
      elseif ($child instanceof TraversableTypedDataInterface) {
        static::validateConfigSchema($child, $violations);
      }
    }
  }

}
