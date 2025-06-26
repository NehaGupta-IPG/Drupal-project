<?php

namespace Drupal\field_hidden_widget_test\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Test entity.
 *
 * @ContentEntityType(
 *   id = "test_entity",
 *   label = @Translation("Test Entity"),
 *   base_table = "test_entity",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *   },
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "add" = "Drupal\Core\Entity\ContentEntityForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm",
 *     },
 *   },
 *   admin_permission = "administer test entities",
 * )
 */
class TestEntity extends ContentEntityBase {

  /**
   * Gets the entity title.
   */
  public static function getTitle($entity) {
    return $entity->get('name')->value ?: t('Test Entity @id', ['@id' => $entity->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['test_field'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Test Hidden Field'))
      ->setSettings([
        'default_value' => 'This should be hidden',
        'max_length' => 255,
        'min' => 0,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'above',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
