<?php

declare(strict_types=1);

namespace Drupal\Core\Entity;

use Drupal\Core\GeneratedUrl;

/**
 * Provides an interface for generating a link target from an entity.
 */
interface EntityLinkTargetInterface {

  /**
   * Gets the generated URL object for a linked entity's link target.
   *
   * @param EntityInterface $entity
   *   A linked entity.
   *
   * @return GeneratedUrl
   *   The generated URL plus cacheability metadata.
   */
  public function getLinkTarget(EntityInterface $entity): GeneratedUrl;

}
