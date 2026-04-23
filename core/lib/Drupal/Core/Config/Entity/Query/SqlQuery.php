<?php

namespace Drupal\Core\Config\Entity\Query;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition as SqlCondition;
use Drupal\Core\Database\Query\JsonCondition;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\ConditionInterface;

/**
 * Config entity query backed by SQL with JSON conditions.
 */
class SqlQuery extends Query {

  public function __construct(EntityTypeInterface $entity_type, $conjunction, ConfigFactoryInterface $config_factory, protected Connection $connection, array $namespaces) {
    parent::__construct($entity_type, $conjunction, $config_factory, $namespaces);
  }

  /**
   * {@inheritdoc}
   */
  protected function loadRecords() {
    $prefix = $this->entityType->getConfigPrefix() . '.';
    $prefix_length = strlen($prefix);
    $id_key = $this->entityType->getKey('id');

    // Search the conditions for restrictions on configuration object names.
    $filter_by_names = NULL;
    if ($this->condition->getConjunction() == 'AND') {
      $lookup_keys = $this->entityType->getLookupKeys();
      $conditions = $this->condition->conditions();
      foreach ($conditions as $condition_key => $condition) {
        $operator = $condition['operator'] ?: (is_array($condition['value']) ? 'IN' : '=');
        if (is_string($condition['field']) && ($operator == 'IN' || $operator == '=')) {
          // Special case ID lookups.
          if ($condition['field'] == $id_key) {
            $filter_by_names = array_map(fn($id) => $prefix . $id, (array) $condition['value']);
            unset($conditions[$condition_key]);
            break;
          }
          elseif (in_array($condition['field'], $lookup_keys)) {
            $query = $this->connection->select('config', 'c')
              ->fields('c', ['name'])
              ->condition('collection', StorageInterface::DEFAULT_COLLECTION)
              ->condition('name', $prefix . '%', 'LIKE');
            $this->addJsonConditions($query, $this->condition);
            $filter_by_names = $query->execute()->fetchCol();
            unset($conditions[$condition_key]);
            break;
          }
        }
      }
    }

    // If no restrictions were found, load all records. Remaining conditions are
    // applied later by execute() via condition->compile(). Using listAll()
    // ensures all configs with this prefix are loaded into the ConfigFactory
    // cache.
    if ($filter_by_names === NULL) {
      $filter_by_names = $this->configFactory->listAll($prefix);
    }

    // Load the corresponding records through ConfigFactory so config overrides
    // are applied.
    $records = [];
    foreach ($this->configFactory->loadMultiple($filter_by_names) as $config) {
      $records[substr($config->getName(), $prefix_length)] = $config->get();
    }
    return $records;
  }

  /**
   * Recursively translates entity query conditions to SQL JSON conditions.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface|\Drupal\Core\Database\Query\Condition $target
   *   The SQL query or condition group to add conditions to.
   * @param \Drupal\Core\Entity\Query\ConditionInterface $conditions
   *   The entity query condition group to translate.
   */
  protected function addJsonConditions(SelectInterface|SqlCondition $target, ConditionInterface $conditions): void {
    $list = $conditions->conditions();
    if (empty($list)) {
      return;
    }

    $group = new SqlCondition($conditions->getConjunction());
    foreach ($list as $condition) {
      if ($condition['field'] instanceof ConditionInterface) {
        $this->addJsonConditions($group, $condition['field']);
      }
      else {
        $operator = $condition['operator'] ?? (is_array($condition['value']) ? 'IN' : '=');
        $group->condition(new JsonCondition('data_query', '$.' . $condition['field'], $condition['value'], $operator));
      }
    }
    $target->condition($group);
  }

}
