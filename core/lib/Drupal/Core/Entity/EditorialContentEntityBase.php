<?php

namespace Drupal\Core\Entity;

/**
 * Provides a base entity class with extended revision and publishing support.
 *
 * @ingroup entity_api
 */
abstract class EditorialContentEntityBase extends ContentEntityBase implements EntityChangedInterface, EntityPublishedInterface, RevisionLogInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;
  use RevisionLogEntityTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Add the revision metadata fields.
    $fields += static::revisionLogBaseFieldDefinitions($entity_type);

    // Add the published field.
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSaveRevision(EntityStorageInterface $storage, \stdClass $record) {
    parent::preSaveRevision($storage, $record);
    $entityType = $this->getEntityType();
    $revision_log_field_name = $entityType->getRevisionMetadataKey('revision_log_message');
    $revision_created_field_name = $entityType->getRevisionMetadataKey('revision_created');
    $new_revision = $this->isNewRevision();
    if (!$new_revision && isset($this->original) && (!isset($record->$revision_log_field_name) || $record->$revision_log_field_name === '')) {
      // If we are updating an existing node without adding a new revision, we
      // need to make sure $entity->revision_log is reset whenever it is empty.
      // Therefore, this code allows us to avoid clobbering an existing log
      // entry with an empty one.
      $record->$revision_log_field_name = $this->original->getRevisionLogMessage();
    }

    if ($new_revision && (empty($record->$revision_created_field_name))) {
      $record->$revision_created_field_name = \Drupal::time()->getRequestTime();
    }
  }

}
