<?php

/**
 * @file
 * Post update functions for Path Alias.
 */

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Implements hook_removed_post_updates().
 */
function path_alias_removed_post_updates(): array {
  return [
    'path_alias_post_update_drop_path_alias_status_index' => '11.0.0',
  ];
}

/**
 * Update the path_alias_revision indices.
 */
function path_alias_post_update_update_path_alias_revision_indexes(): void {
  /** @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $update_manager */
  $update_manager = \Drupal::service('entity.definition_update_manager');
  $entity_type = $update_manager->getEntityType('path_alias');
  $update_manager->updateEntityType($entity_type);
}

/**
 * Update entity definitions for Path Alias to make it revisionable.
 */
function path_alias_post_update_entity_updates(&$sandbox = []): void {
  $definition_update_manager = \Drupal::entityDefinitionUpdateManager();

  $last_installed_schema_repository = \Drupal::service('entity.last_installed_schema.repository');
  $field_storage_definitions = $last_installed_schema_repository->getLastInstalledFieldStorageDefinitions('path_alias');
  $entity_type = $definition_update_manager->getEntityType('path_alias');
  // Update the entity type definition.
  $entity_keys = $entity_type->getKeys();
  $entity_keys['revision'] = 'revision_id';
  $entity_keys['revision_translation_affected'] = 'revision_translation_affected';
  $entity_type->set('entity_keys', $entity_keys);
  $entity_type->set('revision_table', 'path_alias_revision');
  $entity_type->set('revision_data_table', 'path_alias_field_revision');
  $revision_metadata_keys = [
    'revision_default' => 'revision_default',
    'revision_user' => 'revision_uid',
    'revision_created' => 'revision_timestamp',
    'revision_log_message' => 'revision_log',
  ];
  $entity_type->set('revision_metadata_keys', $revision_metadata_keys);

  // Update the field storage definitions for path alias.
  $field_storage_definitions['langcode']->setRevisionable(TRUE);
  $field_storage_definitions['path']->setRevisionable(TRUE);
  $field_storage_definitions['alias']->setRevisionable(TRUE);

  $field_storage_definitions['changed'] = BaseFieldDefinition::create('changed')
    ->setName('changed')
    ->setTargetEntityTypeId('path_alias')
    ->setTargetBundle(NULL)
    ->setLabel(t('Changed'))
    ->setDescription(t('The time when alias was last edited.'))
    ->setRevisionable(TRUE)
    ->setTranslatable(TRUE);

  $field_storage_definitions['revision_id'] = BaseFieldDefinition::create('integer')
    ->setName('revision_id')
    ->setTargetEntityTypeId('path_alias')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision ID'))
    ->setReadOnly(TRUE)
    ->setSetting('unsigned', TRUE);

  $field_storage_definitions['revision_default'] = BaseFieldDefinition::create('boolean')
    ->setName('revision_default')
    ->setTargetEntityTypeId('path_alias')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Default revision'))
    ->setDescription(new TranslatableMarkup('A flag indicating whether this was a default revision when it was saved.'))
    ->setStorageRequired(TRUE)
    ->setInternal(TRUE)
    ->setTranslatable(FALSE)
    ->setRevisionable(TRUE);

  $field_storage_definitions['revision_timestamp'] = BaseFieldDefinition::create('created')
    ->setName('revision_timestamp')
    ->setTargetEntityTypeId('path_alias')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision create time'))
    ->setDescription(new TranslatableMarkup('The time that the current revision was created.'))
    ->setRevisionable(TRUE);

  $field_storage_definitions['revision_uid'] = BaseFieldDefinition::create('entity_reference')
    ->setName('revision_uid')
    ->setTargetEntityTypeId('path_alias')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision user'))
    ->setDescription(new TranslatableMarkup('The user ID of the author of the current revision.'))
    ->setSetting('target_type', 'user')
    ->setRevisionable(TRUE);

  $field_storage_definitions['revision_log'] = BaseFieldDefinition::create('string_long')
    ->setName('revision_log')
    ->setTargetEntityTypeId('path_alias')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision log message'))
    ->setDescription(new TranslatableMarkup('Briefly describe the changes you have made.'))
    ->setRevisionable(TRUE)
    ->setDefaultValue('');

  $definition_update_manager->updateFieldableEntityType($entity_type, $field_storage_definitions, $sandbox);

}
