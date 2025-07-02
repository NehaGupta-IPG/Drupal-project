<?php

namespace Drupal\comment\Plugin\views\sort;

use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\sort\Date;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sort handler for the newer of last comment / entity updated.
 *
 * @ingroup views_sort_handlers
 */
#[ViewsSort("comment_ces_last_updated")]
class StatisticsLastUpdated extends Date {

  /**
   * The node table.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  protected ?string $node_table;

  /**
   * The field alias.
   */
  protected ?string $field_alias;

  /**
   * Constructs a StatisticsLastUpdated object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   */
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
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  protected string $field_alias;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $entity_type = $this->entityTypeManager->getDefinition($this->getEntityType());
    $base_fields = $this->entityFieldManager->getBaseFieldDefinitions($this->getEntityType());
    if ($entity_type->entityClassImplements(EntityChangedInterface::class) && isset($base_fields['changed']) && $base_fields['changed']->getType() === 'changed') {
      // @todo Lookup changed field in keys https://www.drupal.org/node/2209971
      $entity_data_table = $this->query->ensureTable($entity_type->getDataTable(), $this->relationship);
      $this->field_alias = $this->query->addOrderBy(NULL, "GREATEST(" . $entity_data_table . ".changed, " . $this->tableAlias . ".last_comment_timestamp)", $this->options['order'], $this->tableAlias . '_' . $this->field);
      return;
    }
    // No changed field on entity so using own table.
    $this->field_alias = $this->query->addOrderBy(NULL, $this->tableAlias . ".last_comment_timestamp", $this->options['order'], $this->tableAlias . '_' . $this->field);
  }

}
