<?php

namespace Drupal\jsonapi\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Schema\TypeResolver;
use Drupal\Core\Config\TypedConfigManager;
use Drupal\Core\Entity\EntityConstraintViolationList;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\Plugin\DataType\ConfigEntityAdapter;
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
  protected static function validate(EntityInterface $entity, ?array $field_names = NULL) {
    if (!$entity instanceof FieldableEntityInterface && !$entity instanceof ConfigEntityInterface) {
      return;
    }

    // Any config entity received here is guaranteed to be fully validatable.
    // @see \Drupal\jsonapi\ResourceType\ResourceTypeRepository::isMutableResourceType()
    if ($entity instanceof ConfigEntityInterface) {
      $entity = $entity->getTypedData();
    }

    $violations = $entity->validate();

    // Only fully validatable config entities are allowed to be modified. But …
    // some property paths may be dynamically typed (for example for plugin
    // specific settings) they may contain dynamic parts that are not yet fully
    // validatable. Which makes it risky to accept these config changes
    // @see \Drupal\Core\Config\TypedConfigManager::buildDataDefinition()
    if ($entity instanceof ConfigEntityAdapter) {
      $wrapped_entity = $entity->getEntity();
      $config_name = $wrapped_entity->getConfigDependencyName();
      $prefix = $wrapped_entity->getEntityType()->getConfigPrefix();
      $suffix = str_replace($prefix, '', $config_name);
      // Determine the ID parts (separated by periods) in the config dependency
      // name.
      $id_part_count = substr_count($suffix, '.');
      // The config schema type is then: `<prefix>` followed by one `.*` for
      // every ID part.
      $entity_config_schema_type = $prefix . str_repeat('.*', $id_part_count);

      foreach ($entity as $key => $v) {
        // Only mappings or sequences can have dynamic type names.
        // @see \Drupal\Core\Config\Schema\TypeResolver::resolveDynamicTypeName()
        if (!$v instanceof TraversableTypedDataInterface) {
          continue;
        }

        $static_type_root = TypedConfigManager::getStaticTypeRoot($v);
        $static_type_root_type = $static_type_root->getDataDefinition()->getDataType();
        if ($entity_config_schema_type !== $static_type_root_type) {
          $root_type_has_opted_in = FALSE;
          foreach ($static_type_root->getConstraints() as $c) {
            if ($c instanceof FullyValidatableConstraint) {
              $root_type_has_opted_in = TRUE;
              break;
            }
          }

          if ($root_type_has_opted_in) {
            // This was a separate schema type, but it was fully validatable.
            continue;
          }

          $exception = new UnprocessableHttpEntityException();
          $original_type = TypedConfigManager::getOriginalMappingType($v);
          preg_match('/\[(.*)\]/U', $original_type, $matches);
          $expression = $matches[1];
          $reason = TypeResolver::resolveExpression($expression, $v);
          $exception->setViolations(new EntityConstraintViolationList(
            $wrapped_entity,
            [
              // @todo the "editor plugin" part here should still be computed dynamically, by introspecting the schema. Or better yet: determine the provider of the `editor.settings.unicorn` schema type, which is the editor_test module, and provide that in the message.
              new ConstraintViolation("The value at property path $key cannot be validated. Contact the developer of the \"$reason\" editor plugin to make this validatable.", NULL, [], '', $key, NULL),
            ],
          ));
          throw $exception;
        }

        // @todo Add recursion … or add a helper to TypedConfigManager?
      }
    }

    // Remove violations of inaccessible fields as they cannot stem from our
    // changes. Field-level access control only exists for fieldable entities,
    // not for config entities.
    if ($entity instanceof FieldableEntityInterface) {
      $violations->filterByFieldAccess();

      // Filter violations based on the given fields.
      if ($field_names !== NULL) {
        $violations->filterByFields(
          array_diff(array_keys($entity->getFieldDefinitions()), $field_names)
        );
      }
    }

    if (count($violations) > 0) {
      // Instead of returning a generic 400 response we use the more specific
      // 422 Unprocessable Entity code from RFC 4918. That way clients can
      // distinguish between general syntax errors in bad serializations (code
      // 400) and semantic errors in well-formed requests (code 422).
      // @see \Drupal\jsonapi\Normalizer\UnprocessableHttpEntityExceptionNormalizer
      $exception = new UnprocessableHttpEntityException();
      $exception->setViolations($violations);
      throw $exception;
    }
  }

}
