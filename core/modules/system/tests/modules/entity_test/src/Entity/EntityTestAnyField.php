<?php

namespace Drupal\entity_test\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * An entity used for testing any base field values.
 *
 * @ContentEntityType(
 *   id = "entity_test_any_field",
 *   label = @Translation("Entity Test any field"),
 *   base_table = "entity_test_any_field",
 *   entity_keys = {
 *     "uuid" = "uuid",
 *     "id" = "id",
 *     "label" = "name",
 *     "langcode" = "langcode",
 *   },
 *   admin_permission = "administer entity_test content",
 * )
 */
class EntityTestAnyField extends EntityTest {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['data'] = BaseFieldDefinition::create('any')
      ->setLabel(t('Data'))
      ->setDescription(t('Additional data.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED);

    return $fields;
  }

}
