<?php

namespace Drupal\mongodb\modules\content_moderation;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * MongoDB override of ContentModerationStateStorageSchema.
 */
class ContentModerationStateStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);

    // Creates unique keys to guarantee the integrity of the entity and to make
    // the lookup in ModerationStateFieldItemList::getModerationState() fast.
    $unique_keys = [
      'content_entity_type_id',
      'content_entity_id',
      'content_entity_revision_id',
      'workflow',
      'langcode',
    ];
    $schema['content_moderation_state_current_revision']['unique keys'] += [
      'content_moderation_state_current_revision__lookup' => $unique_keys,
    ];
    $schema['content_moderation_state_all_revisions']['unique keys'] += [
      'content_moderation_state_all_revisions__lookup' => $unique_keys,
    ];
    $schema['content_moderation_state_latest_revision']['unique keys'] += [
      'content_moderation_state_latest_revision__lookup' => $unique_keys,
    ];

    return $schema;
  }

}
