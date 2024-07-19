<?php

declare(strict_types=1);

namespace Drupal\jsonapi_translation\Access;

use Drupal\Core\Entity\EntityInterface;
use Drupal\jsonapi\Access\EntityAccessChecker as JsonApiEntityAccessChecker;

/**
 * Checks access to entities.
 *
 * @internal JSON:API Translation maintains no PHP API. The API is the HTTP API.
 *   This class may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/drupal/issues/3032787
 */
final class EntityAccessChecker extends JsonApiEntityAccessChecker {

  /**
   * {@inheritdoc}
   */
  protected function getEntityTranslation(EntityInterface $entity): EntityInterface {
    // JSON:API Translation does not rely on the language negotiation system,
    // instead it performs its own entity translation negotiation based on
    // request data.
    return $entity;
  }

}
