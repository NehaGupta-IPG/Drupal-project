<?php

namespace Drupal\Core\Entity;

use Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a trait for entities that have a weight.
 *
 * @see \Drupal\Core\Entity\EntityWeightInterface
 */
trait EntityWeightTrait {

  /**
   * Returns an array of base field definitions for weight.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type to add the weight field to.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition[]
   *   An array of base field definitions.
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   * Thrown when the entity type does not implement EntityWeightInterface
   * or if it does not have a "weight" entity key.
   */
  public static function weightBaseFieldDefinitions(EntityTypeInterface $entity_type) {
    if (!$entity_type->entityClassImplements(EntityWeightInterface::class)) {
      throw new UnsupportedEntityTypeDefinitionException('The entity type ' . $entity_type->id() . ' does not implement \Drupal\Core\Entity\EntityWeightInterface.');
    }
    if (!$entity_type->hasKey('weight')) {
      throw new UnsupportedEntityTypeDefinitionException('The entity type ' . $entity_type->id() . ' does not have a "weight" entity key.');
    }

    return [
      $entity_type->getKey('weight') => BaseFieldDefinition::create('integer')
        ->setLabel(new TranslatableMarkup('Weight'))
        ->setDescription(new TranslatableMarkup('The weight of this entity.'))
        ->setDefaultValue(0),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): int {
    return (int) $this->getEntityKey('weight');
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight(int $weight): static {
    $key = $this->getEntityType()->getKey('weight');
    $this->set($key, $weight);

    return $this;
  }

}
