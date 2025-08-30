<?php

namespace Drupal\comment\Plugin\views\sort;

use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Attribute\ViewsSort;
use Drupal\views\Plugin\views\sort\Date;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sort handler for the newer of last comment / entity updated.
 *
 * @ingroup views_sort_handlers
 */
#[ViewsSort("comment_ces_last_updated")]
class StatisticsLastUpdated extends Date {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityTypeManagerInterface $entityTypeManager, protected EntityFieldManagerInterface $entityFieldManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $entity_type = $this->entityTypeManager->getDefinition($this->getEntityType());
    $base_fields = $this->entityFieldManager->getBaseFieldDefinitions($this->getEntityType());

    if ($entity_type->entityClassImplements(EntityChangedInterface::class) && isset($base_fields['changed'])) {
      // Entity implements EntityChangedInterface and has a 'changed' field.
      // Use GREATEST() to sort on the newer of entity 'changed' and the last
      // comment timestamp.
      $entity_data_table = $this->query->ensureTable($entity_type->getDataTable(), $this->relationship);
      $this->query->addOrderBy(NULL, "GREATEST(" . $entity_data_table . ".changed, " . $this->tableAlias . ".last_comment_timestamp)", $this->options['order'], $this->tableAlias . '_' . $this->field);
    }
    else {
      // Entity does not implement EntityChangedInterface or lacks a 'changed'
      // field. Sort by the last comment timestamp only.
      $this->query->addOrderBy(NULL, $this->tableAlias . ".last_comment_timestamp", $this->options['order'], $this->tableAlias . '_' . $this->field);
    }
  }

}
