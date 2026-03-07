<?php

namespace Drupal\comment\Plugin\views\field;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\Date;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to display the newer of last comment / node updated.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("comment_ces_last_updated")]
class StatisticsLastUpdated extends Date {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    DateFormatterInterface $date_formatter,
    TimeInterface $time,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $date_formatter, $this->entityTypeManager->getStorage('date_format'), $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('date.formatter'),
      $container->get('datetime.time'),
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
      // Use GREATEST() to return the newer of entity 'changed' and the last
      // comment timestamp.
      $entity_data_table = $this->query->ensureTable($entity_type->getDataTable(), $this->relationship);
      $this->field_alias = $this->query->addField(NULL, "GREATEST(" . $entity_data_table . ".changed, " . $this->tableAlias . ".last_comment_timestamp)", $this->tableAlias . '_' . $this->field);
    }
    else {
      // Entity does not implement EntityChangedInterface or lacks a 'changed'
      // field. Use the last comment timestamp only.
      $this->field_alias = $this->query->addField(NULL, $this->tableAlias . ".last_comment_timestamp", $this->tableAlias . '_' . $this->field);
    }
  }

}
