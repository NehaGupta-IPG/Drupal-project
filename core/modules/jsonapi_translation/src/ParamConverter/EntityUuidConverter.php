<?php

declare(strict_types=1);

namespace Drupal\jsonapi_translation\ParamConverter;

use Drupal\jsonapi\ParamConverter\EntityUuidConverter as JsonApiEntityUuidConverter;

/**
 * Parameter converter for upcasting entity UUIDs to full objects.
 *
 * @internal JSON:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 * @todo Remove when https://www.drupal.org/node/2353611 lands.
 */
final class EntityUuidConverter extends JsonApiEntityUuidConverter {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $entity_type_id = $this->getEntityTypeFromDefaults($definition, $name, $defaults);
    $uuid_key = $this->entityTypeManager->getDefinition($entity_type_id)
      ->getKey('uuid');
    if ($storage = $this->entityTypeManager->getStorage($entity_type_id)) {
      if (!$entities = $storage->loadByProperties([$uuid_key => $value])) {
        return NULL;
      }
      $entity = reset($entities);
      return $entity;
    }
    return NULL;
  }

}
