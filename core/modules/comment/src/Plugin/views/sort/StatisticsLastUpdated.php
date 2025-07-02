<?php

namespace Drupal\comment\Plugin\views\sort;

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

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityTypeManagerInterface $entityTypeManager) {
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $entity_type = $this->entityTypeManager->getDefinition($this->getEntityType());
    $entity_data_table = $this->query->ensureTable($entity_type->getDataTable(), $this->relationship);
    $this->query->addOrderBy(NULL, "GREATEST(" . $entity_data_table . ".changed, " . $this->tableAlias . ".last_comment_timestamp)", $this->options['order'], $this->tableAlias . '_' . $this->field);
  }

}
