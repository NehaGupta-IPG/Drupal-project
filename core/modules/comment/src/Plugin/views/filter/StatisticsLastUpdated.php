<?php

namespace Drupal\comment\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\Date;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter handler for the newer of last comment / node updated.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter("comment_ces_last_updated")]
class StatisticsLastUpdated extends Date {

  /**
   * The node table.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  protected ?string $node_table;

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
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $entity_type = $this->entityTypeManager->getDefinition($this->getEntityType());
    $entity_data_table = $this->query->ensureTable($entity_type->getDataTable(), $this->relationship);
    $field = 'GREATEST(' . $entity_data_table . '.changed, ' . $this->tableAlias . '.last_comment_timestamp)';
    $info = $this->operators();
    if (!empty($info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}($field);
    }
  }

}
