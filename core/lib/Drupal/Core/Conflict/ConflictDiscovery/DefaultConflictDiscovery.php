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
    $ours_entity = $event->getOursEntity();
    if (!$ours_entity instanceof ContentEntityInterface) {
      return;
    }
    $theirs_entity = $event->getTheirsEntity();
    $base_entity = $event->getBaseEntity();
    // This is enforced by the event constructor.
    \assert($theirs_entity instanceof ContentEntityInterface && $base_entity instanceof ContentEntityInterface);

    $langcode = $ours_entity->language()->getId();
    $theirs_entity = $theirs_entity->getTranslation($langcode);
    $base_entity = $base_entity->getTranslation($langcode);

    // The revision metadata fields are updated constantly, and they will always
    // cause conflicts, therefore we skip them here.
    // @see \Drupal\Core\Entity\ContentEntityForm::buildEntity().
    $skip_fields = array_flip($ours_entity->getEntityType()->getRevisionMetadataKeys());

    // Iterate over the entity fields to find conflicting properties.
    foreach ($ours_entity->getFields() as $field_name => $field_items_ours) {
      if (isset($skip_fields[$field_name])) {
        continue;
      }

      $field_definition = $field_items_ours->getFieldDefinition();
      // There cannot be conflicts on read-only fields.
      if ($field_definition->isReadOnly()) {
        continue;
      }

      $field_items_theirs = $theirs_entity->get($field_name);
      $field_items_base = $base_entity->get($field_name);

      // Check for changes between ours, theirs and base versions.
      if (!$field_items_ours->equals($field_items_theirs) && !$field_items_theirs->equals($field_items_base)) {
        // Check for changes between the ours and the base entity versions.
        $conflict_type = $field_items_ours->equals($field_items_base) ? ConflictType::TheirsOnly : ConflictType::Both;
        $event->addConflict($field_name, $conflict_type);
      }
    }
  }

}
