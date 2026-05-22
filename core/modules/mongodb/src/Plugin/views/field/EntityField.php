<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\mongodb\modules\views\EntityFieldRenderer;
use Drupal\views\Plugin\views\field\EntityField as CoreEntityField;

/**
 * Overriding the views field plugin "field".
 */
class EntityField extends CoreEntityField {

  use FieldPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function clickSort($order) {
    // No column selected, can't continue.
    if (empty($this->options['click_sort_column'])) {
      return;
    }

    $this->ensureMyTable();
    $field_storage_definition = $this->getFieldStorageDefinition();
    $column = $this->getTableMapping()->getFieldColumnName($field_storage_definition, $this->options['click_sort_column']);
    if (!isset($this->aliases[$column])) {
      // Column is not in query; add a sort on it (without adding the column).
      $this->aliases[$column] = $this->tableAlias . '.' . $column;
    }

    if (isset($this->definition['real field'])) {
      $alias = $this->tableAlias . '_' . str_replace('.', '_', $this->definition['real field']);
      // The field needs to be there for sorting.
      $this->query->addField($this->tableAlias, $this->definition['real field'], $alias);
      $this->query->addOrderBy(NULL, NULL, $order, $alias);
    }
    else {
      $this->query->addOrderBy(NULL, NULL, $order, $this->aliases[$column]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityFieldRenderer() {
    if (!isset($this->entityFieldRenderer)) {
      // This can be invoked during field handler initialization in which case
      // view fields are not set yet.
      if (!empty($this->view->field)) {
        foreach ($this->view->field as $field) {
          // An entity field renderer can handle only a single relationship.
          if ($field->relationship == $this->relationship && isset($field->entityFieldRenderer)) {
            $this->entityFieldRenderer = $field->entityFieldRenderer;
            break;
          }
        }
      }
      if (!isset($this->entityFieldRenderer)) {
        $entity_type = $this->entityTypeManager->getDefinition($this->getEntityType());
        // MongoDB has its own version of the class EntityFieldRenderer and
        // therefore must the function be overridden to get an instance of the
        // MongoDB version of the class.
        $this->entityFieldRenderer = new EntityFieldRenderer($this->view, $this->relationship, $this->languageManager, $entity_type, $this->entityTypeManager, $this->entityRepository);
      }
    }
    return $this->entityFieldRenderer;
  }

}
