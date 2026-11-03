<?php

namespace Drupal\jsonapi_test_revision_reference_types\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\TranslatableRevisionableInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Field\PreconfiguredFieldUiOptionsInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataReferenceDefinition;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\entity_reference_revisions\EntityNeedsSaveInterface;

/**
 * Revision Entity reference field type that mocks contrib.
 *
 * @see https://www.drupal.org/project/drupal/issues/3088239
 * @see \Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem
 *
 * @FieldType(
 *   id = "jsonapi_test_node_revision_reference",
 * )
 */
class NodeRevisionReferenceItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['target_revision_id'] = DataReferenceTargetDefinition::create('integer')
      ->setLabel('Node revision ID')
      ->setSetting('unsigned', TRUE);

    $properties['entity'] = DataReferenceDefinition::create('entity_revision')
      ->setLabel('Node revision')
      ->setDescription(t('The referenced node revision'))
      // The entity object is computed out of the entity ID.
      ->setComputed(TRUE)
      ->setReadOnly(FALSE)
      ->setTargetDefinition(EntityDataDefinition::create('node'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);
    $schema['columns']['target_revision_id'] = array(
      'description' => 'The revision ID of the target entity.',
      'type' => 'int',
      'unsigned' => TRUE,
    );
    $schema['indexes']['target_revision_id'] = array('target_revision_id');
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if (isset($values) && !is_array($values)) {
      // If either a scalar or an object was passed as the value for the item,
      // assign it to the 'entity' property since that works for both cases.
      $this->set('entity', $values, $notify);
    }
    else {
      parent::setValue($values, FALSE);
      // Support setting the field item with only one property, but make sure
      // values stay in sync if only property is passed.
      // NULL is a valid value, so we use array_key_exists().
      if (is_array($values) && array_key_exists('target_id', $values) && !isset($values['entity'])) {
        $this->onChange('target_id', FALSE);
      }
      elseif (is_array($values) && array_key_exists('target_revision_id', $values) && !isset($values['entity'])) {
        $this->onChange('target_revision_id', FALSE);
      }
      elseif (is_array($values) && !array_key_exists('target_id', $values) && !array_key_exists('target_revision_id', $values) && isset($values['entity'])) {
        $this->onChange('entity', FALSE);
      }
      elseif (is_array($values) && array_key_exists('target_id', $values) && isset($values['entity'])) {
        // If both properties are passed, verify the passed values match. The
        // only exception we allow is when we have a new entity: in this case
        // its actual id and target_id will be different, due to the new entity
        // marker.
        $entity_id = $this->get('entity')->getTargetIdentifier();
        // If the entity has been saved and we're trying to set both the
        // target_id and the entity values with a non-null target ID, then the
        // value for target_id should match the ID of the entity value.
        if (!$this->entity->isNew() && $values['target_id'] !== NULL && ($entity_id != $values['target_id'])) {
          throw new \InvalidArgumentException('The target id and entity passed to the entity reference item do not match.');
        }
      }
      // Notify the parent if necessary.
      if ($notify && $this->getParent()) {
        $this->getParent()->onChange($this->getName());
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $values = parent::getValue();
    if ($this->entity instanceof EntityNeedsSaveInterface && $this->entity->needsSave()) {
      $values['entity'] = $this->entity;
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    // Make sure that the target ID and the target property stay in sync.
    if ($property_name == 'entity') {
      $property = $this->get('entity');
      $target_id = $property->isTargetNew() ? NULL : $property->getTargetIdentifier();
      $this->writePropertyValue('target_id', $target_id);
      $this->writePropertyValue('target_revision_id', $property->getValue()->getRevisionId());
    }
    elseif ($property_name == 'target_id' && $this->target_id != NULL && $this->target_revision_id) {
      $this->writePropertyValue('entity', array(
        'target_id' => $this->target_id,
        'target_revision_id' => $this->target_revision_id,
      ));
    }
    elseif ($property_name == 'target_revision_id' && $this->target_revision_id && $this->target_id) {
      $this->writePropertyValue('entity', array(
        'target_id' => $this->target_id,
        'target_revision_id' => $this->target_revision_id,
      ));
    }
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // Avoid loading the entity by first checking the 'target_id'.
    if ($this->target_id !== NULL && $this->target_revision_id !== NULL) {
      return FALSE;
    }
    if ($this->entity && $this->entity instanceof EntityInterface) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    $has_new = $this->hasNewEntity();

    // If it is a new entity, parent will save it.
    parent::preSave();

    $is_affected = TRUE;
    if (!$has_new) {
      // Create a new revision if it is a composite entity in a host with a new
      // revision.

      $host = $this->getEntity();
      $needs_save = $this->entity instanceof EntityNeedsSaveInterface && $this->entity->needsSave();

      // The item is considered to be affected if the field is either
      // untranslatable or there are translation changes. This ensures that for
      // translatable fields, a new revision of the referenced entity is only
      // created for the affected translations and that the revision ID does not
      // change on the unaffected translations. In turn, the host entity is not
      // marked as affected for these translations.
      $is_affected = !$this->getFieldDefinition()->isTranslatable() || ($host instanceof TranslatableRevisionableInterface && $host->hasTranslationChanges());
      if ($is_affected && !$host->isNew() && $this->entity && $this->entity->getEntityType()->get('entity_revision_parent_id_field')) {
        if ($host->isNewRevision()) {
          $this->entity->setNewRevision();
          $needs_save = TRUE;
        }
        // Additionally ensure that the default revision state is kept in sync.
        if ($this->entity && $host->isDefaultRevision() != $this->entity->isDefaultRevision()) {
          $this->entity->isDefaultRevision($host->isDefaultRevision());
          $needs_save = TRUE;
        }
      }
      if ($needs_save) {

        // Because ContentEntityBase::hasTranslationChanges() does not check for
        // EntityReferenceRevisionsFieldItemList::hasAffectingChanges() on field
        // items that are not translatable, hidden on translation forms and not
        // in the default translation, this has to be handled here by setting
        // setRevisionTranslationAffected on host translations that holds a
        // reference that has been changed.
        if ($is_affected && $host instanceof TranslatableRevisionableInterface) {
          $languages = $host->getTranslationLanguages();
          foreach ($languages as $langcode => $language) {
            $translation = $host->getTranslation($langcode);
            if ($this->entity->hasTranslation($langcode) && $this->entity->getTranslation($langcode)->hasTranslationChanges() && $this->target_revision_id != $this->entity->getRevisionId()) {
              $translation->setRevisionTranslationAffected(TRUE);
              $translation->setRevisionTranslationAffectedEnforced(TRUE);
            }
          }
        }

        $this->entity->save();
      }
    }
    if ($this->entity && $is_affected) {
      $this->target_revision_id = $this->entity->getRevisionId();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    parent::postSave($update);

    $needs_save = FALSE;
    // If any of entity, parent type or parent id is missing then return.
    if (!$this->entity || !$this->entity->getEntityType()->get('entity_revision_parent_type_field') || !$this->entity->getEntityType()->get('entity_revision_parent_id_field')) {
      return;
    }

    $entity = $this->entity;
    $parent_entity = $this->getEntity();

    // If the entity has a parent field name get the key.
    if ($entity->getEntityType()->get('entity_revision_parent_field_name_field')) {
      $parent_field_name = $entity->getEntityType()->get('entity_revision_parent_field_name_field');

      // If parent field name has changed then set it.
      if ($entity->get($parent_field_name)->value != $this->getFieldDefinition()->getName()) {
        $entity->set($parent_field_name, $this->getFieldDefinition()->getName());
        $needs_save = TRUE;
      }
    }

    // Keep in sync the translation languages between the parent and the child.
    // For non translatable fields we have to do this in ::preSave but for
    // translatable fields we have all the information we need in ::delete.
    if (isset($parent_entity->original) && !$this->getFieldDefinition()->isTranslatable()) {
      $langcodes = array_keys($parent_entity->getTranslationLanguages());
      $original_langcodes = array_keys($parent_entity->original->getTranslationLanguages());
      if ($removed_langcodes = array_diff($original_langcodes, $langcodes)) {
        foreach ($removed_langcodes as $removed_langcode) {
          if ($entity->hasTranslation($removed_langcode)  && $entity->getUntranslated()->language()->getId() != $removed_langcode) {
            $entity->removeTranslation($removed_langcode);
          }
        }
        $needs_save = TRUE;
      }
    }

    $parent_type = $entity->getEntityType()->get('entity_revision_parent_type_field');
    $parent_id = $entity->getEntityType()->get('entity_revision_parent_id_field');

    // If the parent type has changed then set it.
    if ($entity->get($parent_type)->value != $parent_entity->getEntityTypeId()) {
      $entity->set($parent_type, $parent_entity->getEntityTypeId());
      $needs_save = TRUE;
    }
    // If the parent id has changed then set it.
    if ($entity->get($parent_id)->value != $parent_entity->id()) {
      $entity->set($parent_id, $parent_entity->id());
      $needs_save = TRUE;
    }

    if ($needs_save) {
      // Check if any of the keys has changed, save it, do not create a new
      // revision.
      $entity->setNewRevision(FALSE);
      $entity->save();
    }
  }

}

