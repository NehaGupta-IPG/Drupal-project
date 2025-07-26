<?php

namespace Drupal\content_moderation;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Defines the content moderation state schema handler.
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
    if ($data_table = $this->storage->getDataTable()) {
      $schema[$data_table]['unique keys'] += [
        'content_moderation_state__lookup' => $unique_keys,
      ];
    }
    if ($revision_data_table = $this->storage->getRevisionDataTable()) {
      $schema[$revision_data_table]['unique keys'] += [
        'content_moderation_state__lookup' => $unique_keys,
      ];
      // Add an index on the content_entity_revision_id column to make
      // moderation_state computed field lookups faster.
      $schema[$revision_data_table]['indexes'] += [
        'content_moderation_state__content_entity_revision_id' => ['content_entity_revision_id'],
      ];
    }

    return $schema;
  }

}
