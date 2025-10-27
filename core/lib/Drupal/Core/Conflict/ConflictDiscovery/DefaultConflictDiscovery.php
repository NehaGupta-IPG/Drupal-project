<?php

declare(strict_types=1);

namespace Drupal\Core\Conflict\ConflictDiscovery;

use Drupal\Core\Conflict\ConflictType;
use Drupal\Core\Conflict\Event\EntityConflictDiscoveryEvent;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines a default implementation of conflict discovery.
 */
class DefaultConflictDiscovery extends ConflictDiscoveryBase {

  /**
   * {@inheritdoc}
   */
  public function discoverContentEntityConflicts(EntityConflictDiscoveryEvent $event): void {
    $local_entity = $event->getLocalEntity();
    if (!$local_entity instanceof ContentEntityInterface) {
      return;
    }
    $remote_entity = $event->getRemoteEntity();
    $base_entity = $event->getBaseEntity();
    // This is enforced by the event constructor.
    \assert($remote_entity instanceof ContentEntityInterface && $base_entity instanceof ContentEntityInterface);

    $langcode = $local_entity->language()->getId();
    $remote_entity = $remote_entity->getTranslation($langcode);
    $base_entity = $base_entity->getTranslation($langcode);

    // The revision metadata fields are updated constantly, and they will always
    // cause conflicts, therefore we skip them here.
    // @see \Drupal\Core\Entity\ContentEntityForm::buildEntity().
    $skip_fields = array_flip($local_entity->getEntityType()->getRevisionMetadataKeys());

    // Iterate over the entity fields to find conflicting properties.
    foreach ($local_entity->getFields() as $field_name => $field_items_local) {
      if (isset($skip_fields[$field_name])) {
        continue;
      }

      $field_definition = $field_items_local->getFieldDefinition();
      // There cannot be conflicts on read only fields.
      if ($field_definition->isReadOnly()) {
        continue;
      }

      $field_items_remote = $remote_entity->get($field_name);
      $field_items_base = $base_entity->get($field_name);

      // Check for changes between the local and the remote entity versions.
      if (!$field_items_local->equals($field_items_remote)) {
        // Check for changes between the remote and the base entity versions.
        if (!$field_items_remote->equals($field_items_base)) {
          // Check for changes between the local and the base entity versions.
          $conflict_type = $field_items_local->equals($field_items_base) ? ConflictType::Remote : ConflictType::LocalRemote;
          $event->addConflict($field_name, $conflict_type);
        }
      }
    }
  }

}
