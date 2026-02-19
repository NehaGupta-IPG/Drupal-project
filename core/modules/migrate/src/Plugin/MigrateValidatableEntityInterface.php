<?php

namespace Drupal\migrate\Plugin;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * To implement by a destination plugin that should provide entity validation.
 *
 * @ingroup migration
 */
interface MigrateValidatableEntityInterface {

  /**
   * Returns a state of whether an entity needs to be validated before saving.
   *
   * @param FieldableEntityInterface $entity
   *   The entity to check for required validation.
   *
   * @return bool
   *   A state of whether an entity needs to be validated.
   */
  public function isEntityValidationRequired(FieldableEntityInterface $entity);

  /**
   * Validates the entity.
   *
   * @param FieldableEntityInterface $entity
   *   The entity to validate.
   *
   * @throws EntityValidationException
   *   When the validation didn't succeed.
   */
  public function validateEntity(FieldableEntityInterface $entity);

}
