<?php

namespace Drupal\editor;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Helper for entity references.
 */
class EntityReferenceHelper {

  /**
   * Returns the entity reference revisions on an entity.
   *
   * These are the entities referenced by entity reference revisions fields,
   * which are implemented by contrib module "entity_reference_revisions".
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   An entity whose fields to analyze.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The referenced entities.
   */
  public function getEntityReferenceRevisions(FieldableEntityInterface $entity): array {
    $result = [];
    $field_definitions = $entity->getFieldDefinitions();
    foreach ($field_definitions as $field => $field_definition) {
      if ($field_definition->getType() === 'entity_reference_revisions') {
        $entity_reference_revision = $entity->get($field)->entity;
        $result[] = $entity_reference_revision;
        if ($entity_reference_revision instanceof FieldableEntityInterface) {
          $result = array_merge($result, $this->getEntityReferenceRevisions($entity_reference_revision));
        }
      }
    }
    return $result;
  }

}
