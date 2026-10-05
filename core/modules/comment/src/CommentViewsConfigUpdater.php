<?php

declare(strict_types=1);

namespace Drupal\comment;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\views\ViewEntityInterface;

/**
 * Updates views that filter on the status of a comment field.
 *
 * @internal
 */
class CommentViewsConfigUpdater implements ContainerInjectionInterface {

  use AutowireTrait;

  /**
   * The status columns of comment fields, keyed by table and column name.
   */
  protected array $statusColumns;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Converts numeric filters on comment field status columns.
   *
   * Comment field status columns used the numeric filter before they used the
   * node_comment filter. A numeric operator is replaced with the "in" or
   * "not in" operator and the commenting statuses that match the numeric
   * condition. The "empty" and "not empty" operators are kept. A filter is
   * left unchanged when no statuses match or when its operator, such as
   * "regular_expression", has no equivalent.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The view to update.
   * @param string[] $skipped
   *   Filters that were left unchanged, as "display_id:filter_id".
   *
   * @return bool
   *   Whether the view was updated.
   */
  public function processStatusFilters(ViewEntityInterface $view, array &$skipped = []): bool {
    $columns = $this->getStatusColumns();
    $changed = FALSE;
    $displays = $view->get('display');
    foreach ($displays as $display_id => $display) {
      foreach ($display['display_options']['filters'] ?? [] as $filter_id => $filter) {
        if (($filter['plugin_id'] ?? NULL) !== 'numeric' || !isset($columns[$filter['table'] ?? ''][$filter['field'] ?? ''])) {
          continue;
        }
        $filter = $this->convertFilter($filter);
        if ($filter === NULL) {
          $skipped[] = $display_id . ':' . $filter_id;
          continue;
        }
        $displays[$display_id]['display_options']['filters'][$filter_id] = $filter;
        $changed = TRUE;
      }
    }
    if ($changed) {
      $view->set('display', $displays);
    }
    return $changed;
  }

  /**
   * Gets the status columns of comment fields.
   *
   * @return array
   *   An array keyed by table name, then by column name.
   */
  protected function getStatusColumns(): array {
    if (!isset($this->statusColumns)) {
      $this->statusColumns = [];
      /** @var \Drupal\field\FieldStorageConfigInterface[] $field_storages */
      $field_storages = $this->entityTypeManager->getStorage('field_storage_config')->loadByProperties(['type' => 'comment']);
      foreach ($field_storages as $field_storage) {
        $entity_type_id = $field_storage->getTargetEntityTypeId();
        $storage = $this->entityTypeManager->getStorage($entity_type_id);
        if (!$storage instanceof SqlEntityStorageInterface) {
          continue;
        }
        $table_mapping = $storage->getTableMapping();
        $column = $table_mapping->getFieldColumnName($field_storage, 'status');
        $this->statusColumns[$table_mapping->getDedicatedDataTableName($field_storage)][$column] = TRUE;
        if ($this->entityTypeManager->getDefinition($entity_type_id)->isRevisionable()) {
          $this->statusColumns[$table_mapping->getDedicatedRevisionTableName($field_storage)][$column] = TRUE;
        }
      }
    }
    return $this->statusColumns;
  }

  /**
   * Converts a numeric filter to a node_comment filter.
   *
   * @param array $filter
   *   The numeric filter.
   *
   * @return array|null
   *   The node_comment filter, or NULL if the filter cannot be converted.
   */
  protected function convertFilter(array $filter): ?array {
    $condition = $this->convertCondition($filter['operator'] ?? '=', $filter['value'] ?? []);
    if ($condition === NULL) {
      return NULL;
    }
    [$filter['operator'], $filter['value']] = $condition;

    foreach ($filter['group_info']['group_items'] ?? [] as $key => $item) {
      $condition = $this->convertCondition($item['operator'] ?? '=', $item['value'] ?? []);
      if ($condition === NULL) {
        return NULL;
      }
      [$item['operator'], $item['value']] = $condition;
      $filter['group_info']['group_items'][$key] = $item;
    }

    if (isset($filter['expose'])) {
      unset($filter['expose']['min_placeholder'], $filter['expose']['max_placeholder'], $filter['expose']['placeholder'], $filter['expose']['min_label'], $filter['expose']['max_label']);
      $filter['expose']['reduce'] ??= FALSE;
      if (!empty($filter['expose']['operator_list'])) {
        $operator_list = [];
        foreach ($filter['expose']['operator_list'] as $operator) {
          $operator = match ($operator) {
            '=' => 'in',
            '!=' => 'not in',
            'empty', 'not empty' => $operator,
            default => NULL,
          };
          if ($operator !== NULL) {
            $operator_list[$operator] = $operator;
          }
        }
        $filter['expose']['operator_list'] = $operator_list;
      }
    }

    $filter['plugin_id'] = 'node_comment';
    return $filter;
  }

  /**
   * Converts a numeric operator and value to a node_comment operator and value.
   *
   * @param string $operator
   *   The numeric operator.
   * @param mixed $value
   *   The numeric value, with "value", "min" and "max" keys.
   *
   * @return array|null
   *   The operator and value, or NULL if they cannot be converted.
   */
  protected function convertCondition(string $operator, mixed $value): ?array {
    if ($operator === 'empty' || $operator === 'not empty') {
      return [$operator, []];
    }
    if (!in_array($operator, ['=', '!=', '<', '<=', '>', '>=', 'between', 'not between'], TRUE)) {
      return NULL;
    }
    $value = is_array($value) ? $value : ['value' => $value];
    $value += ['value' => '', 'min' => '', 'max' => ''];
    $between = $operator === 'between' || $operator === 'not between';

    // A filter without a value adds no condition.
    if (($between ? $value['min'] . $value['max'] : $value['value']) === '') {
      return [$operator === '!=' || $operator === 'not between' ? 'not in' : 'in', []];
    }

    if ($operator === '=' || $operator === '!=') {
      $status = filter_var($value['value'], FILTER_VALIDATE_INT);
      if ($status === FALSE || CommentingStatus::tryFrom($status) === NULL) {
        return NULL;
      }
      return [$operator === '=' ? 'in' : 'not in', [$status => (string) $status]];
    }

    $min = is_numeric($value['min']) ? (float) $value['min'] : NULL;
    $max = is_numeric($value['max']) ? (float) $value['max'] : NULL;
    $number = is_numeric($value['value']) ? (float) $value['value'] : NULL;
    if ($between ? $min === NULL && $max === NULL : $number === NULL) {
      return NULL;
    }
    // Match the conditions the numeric filter adds to the query.
    $statuses = [];
    foreach (CommentingStatus::cases() as $status) {
      $s = $status->value;
      $match = match ($operator) {
        '<' => $s < $number,
        '<=' => $s <= $number,
        '>' => $s > $number,
        '>=' => $s >= $number,
        'between' => ($min === NULL || $s >= $min) && ($max === NULL || $s <= $max),
        'not between' => $min !== NULL && $max !== NULL ? $s < $min || $s > $max : ($min !== NULL ? $s < $min : $s > $max),
      };
      if ($match) {
        $statuses[$s] = (string) $s;
      }
    }
    return $statuses ? ['in', $statuses] : NULL;
  }

}
