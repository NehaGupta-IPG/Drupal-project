<?php

namespace Drupal\views\Plugin\views\argument;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\views\Attribute\ViewsArgument;

/**
 * Argument handler to accept an entity ID value.
 *
 * This handler accepts the identifiers of entities themselves. The definition
 * defines the `entity_type` parameter to determine what kind of ID to load.
 * Entity reference ID values are handled by EntityReferenceArgument.
 *
 * @see \Drupal\views\Plugin\views\argument\EntityReferenceArgument
 *
 * @ingroup views_argument_handlers
 */
#[ViewsArgument(
  id: 'entity_id',
)]
class EntityArgument extends NumericArgument implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityRepositoryInterface $entityRepository,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function titleQuery() {
    $titles = [];

    $entities = $this->entityTypeManager->getStorage($this->definition['entity_type'])->loadMultiple($this->value);
    foreach ($entities as $entity) {
      $titles[$entity->id()] = $this->entityRepository->getTranslationFromContext($entity)->label();
    }
    return $titles;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinition() {
    // Deliberately bypass NumericArgument::getContextDefinition(), which
    // falls back to a generic 'integer' context: ask the configured
    // validator plugin directly so an entity-typed fallback below can still
    // apply when no validator is configured.
    if ($context_definition = $this->getPlugin('argument_validator')->getContextDefinition()) {
      return $context_definition;
    }

    $entity_type_id = $this->definition['entity_type'] ?? NULL;
    if (!$entity_type_id || !$this->entityTypeManager->hasDefinition($entity_type_id)) {
      return NULL;
    }

    return EntityContextDefinition::fromEntityTypeId($entity_type_id)
      ->setLabel($this->adminLabel())
      ->setRequired(FALSE);
  }

}
