<?php

namespace Drupal\Core\Action\Plugin\Action\Derivative;

use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;

/**
 * Provides an action deriver that finds entity types of EntityChangedInterface.
 *
 * @see \Drupal\Core\Action\Plugin\Action\SaveAction
 */
class EntitySaveActionDeriver extends EntityActionDeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (empty($this->derivatives)) {
      $definitions = [];
      foreach ($this->getApplicableEntityTypes() as $entity_type_id => $entity_type) {
        $definition = $base_plugin_definition;
        $definition['context_definitions'] = [
          $entity_type_id => EntityContextDefinition::fromEntityType($entity_type)
            ->setDescription('The entity to perform the action on.'),
        ];
        $definition['provides'] = [
          $entity_type_id => EntityContextDefinition::fromEntityType($entity_type)
            ->setDescription('The entity object, if newly created.')->setRequired(FALSE),
        ];
        $definition['label'] = sprintf('%s %s', $base_plugin_definition['action_label'], $entity_type->getSingularLabel());
        $definitions[$entity_type_id] = $definition;
      }
      $this->derivatives = $definitions;
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }
  /**
   * {@inheritdoc}
   */
  protected function isApplicable(EntityTypeInterface $entity_type) {
    return $entity_type->entityClassImplements(EntityChangedInterface::class);
  }

}
