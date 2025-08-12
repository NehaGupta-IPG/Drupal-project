<?php

namespace Drupal\comment\Plugin\views\filter;

use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
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
      $container->get('entity_field.manager')
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
      // Use GREATEST function to filter by the newer of entity changed time and last comment time.
      $entity_data_table = $this->query->ensureTable($entity_type->getDataTable(), $this->relationship);
      $field = 'GREATEST(' . $entity_data_table . '.changed, ' . $this->tableAlias . '.last_comment_timestamp)';
    }
    else {
      // Entity doesn't implement EntityChangedInterface or doesn't have a 'changed' field.
      // Just filter by the last comment timestamp.
      $field = $this->tableAlias . '.last_comment_timestamp';
    }

    $info = $this->operators();
    if (!empty($info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}($field);
    }
  }

}
