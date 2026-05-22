<?php

namespace Drupal\mongodb\modules\views;

use Drupal\views\ManyToOneHelper as CoreManyToOneHelper;
use Drupal\views\Plugin\views\HandlerBase;
use Drupal\views\Plugin\views\join\JoinPluginBase;

/**
 * Overrides the class \Drupal\views\ManyToOneHelper.
 */
class ManyToOneHelper extends CoreManyToOneHelper {

  /**
   * Get the field via formula or build it using alias and field name.
   *
   * Sometimes the handler might want us to use some kind of formula, so give
   * it that option. If it wants us to do this, it must set
   * $helper->formula = TRUE and implement handler->getFormula().
   */
  public function getField() {
    if (!empty($this->formula)) {
      return $this->handler->getFormula();
    }
    elseif ($this->handler->table == $this->handler->view->storage->get('base_table')) {
      return $this->handler->realField;
    }
    return $this->handler->tableAlias . '.' . $this->handler->realField;
  }

  /**
   * Add a table to the query.
   *
   * This is an advanced concept; not only does it add a new instance of the
   * table, but it follows the relationship path all the way down to the
   * relationship link point and adds *that* as a new relationship and then
   * adds the table to the relationship, if necessary.
   */
  public function addTable($join = NULL, $alias = NULL) {
    // This is used for lookups in the many_to_one table.
    $field = $this->handler->relationship . '_' . $this->handler->table . '.' . $this->handler->field;

    if (empty($join)) {
      $join = $this->getJoin();
    }

    // See if there's a chain between us and the base relationship. If so, we
    // need to create a new relationship to use.
    $relationship = $this->handler->relationship;

    // Determine the primary table to seek.
    if (empty($this->handler->query->relationships[$relationship])) {
      $base_table = $this->handler->view->storage->get('base_table');
    }
    else {
      $base_table = $this->handler->query->relationships[$relationship]['base'];
    }

    if ($join instanceof JoinPluginBase) {
      // Cycle through the joins. This isn't as error-safe as the normal
      // ensurePath logic. Perhaps it should be.
      $r_join = clone $join;
      while ($r_join->leftTable != $base_table) {
        $r_join = HandlerBase::getTableJoin($r_join->leftTable, $base_table);
      }
      // If we found that there are tables in between, add the relationship.
      if ($r_join->table != $join->table) {
        $relationship = $this->handler->query->addRelationship($this->handler->table . '_' . $r_join->table, $r_join, $r_join->table, $this->handler->relationship);
      }

      // And now add our table, using the new relationship if one was used.
      $alias = $this->handler->query->addTable($this->handler->table, $relationship, $join, $alias);
    }

    // Store what values are used by this table chain so that other chains can
    // automatically discard those values.
    if (empty($this->handler->view->many_to_one_tables[$field])) {
      $this->handler->view->many_to_one_tables[$field] = $this->handler->value;
    }
    else {
      $this->handler->view->many_to_one_tables[$field] = array_merge($this->handler->view->many_to_one_tables[$field], $this->handler->value);
    }

    return $alias;
  }

  /**
   * Override ensureMyTable so we can control how this joins in.
   *
   * The operator actually has influence over joining.
   */
  public function ensureMyTable() {
    if (!isset($this->handler->tableAlias)) {
      // Case 1: Operator is an 'or' and we're not reducing duplicates.
      // We hence get the absolute simplest:
      $field = $this->handler->relationship . '_' . $this->handler->table . '.' . $this->handler->field;
      if ($this->handler->operator == 'or' && empty($this->handler->options['reduce_duplicates'])) {
        if (empty($this->handler->options['add_table']) && empty($this->handler->view->many_to_one_tables[$field])) {
          // Query optimization, INNER joins are slightly faster, so use them
          // when we know we can.
          $join = $this->getJoin();
          if (isset($join)) {
            $join->type = 'INNER';
          }
          $this->handler->tableAlias = $this->handler->query->ensureTable($this->handler->table, $this->handler->relationship, $join);
          $this->handler->view->many_to_one_tables[$field] = $this->handler->value;
        }
        else {
          $join = $this->getJoin();
          $join->type = 'LEFT';
          if (!empty($this->handler->view->many_to_one_tables[$field])) {
            foreach ($this->handler->view->many_to_one_tables[$field] as $value) {
              $join->extra = [
                [
                  'field' => $this->handler->realField,
                  'operator' => '!=',
                  'value' => $value,
                  'numeric' => !empty($this->handler->definition['numeric']),
                ],
              ];
            }
          }

          $this->handler->tableAlias = $this->addTable($join);
        }

        return $this->handler->tableAlias;
      }

      // Case 2: it's an 'and' or an 'or'.
      // We do one join per selected value.
      if ($this->handler->operator != 'not') {
        // Clone the join for each table:
        $this->handler->tableAliases = [];
        foreach ($this->handler->value as $value) {
          $join = $this->getJoin();
          if ($join instanceof JoinPluginBase) {
            if ($this->handler->operator == 'and') {
              $join->type = 'INNER';
            }
            $join->extra = [
              [
                'field' => $this->handler->realField,
                'value' => $value,
                'numeric' => !empty($this->handler->definition['numeric']),
              ],
            ];
          }

          // The table alias needs to be unique to this value across the
          // multiple times the filter or argument is called by the view.
          if (!isset($this->handler->view->many_to_one_aliases[$field][$value])) {
            if (!isset($this->handler->view->many_to_one_count[$this->handler->table])) {
              $this->handler->view->many_to_one_count[$this->handler->table] = 0;
            }
            $this->handler->view->many_to_one_aliases[$field][$value] = $this->handler->table . '_value_' . ($this->handler->view->many_to_one_count[$this->handler->table]++);
          }

          if ($join) {
            $this->handler->tableAliases[$value] = $this->addTable($join, $this->handler->view->many_to_one_aliases[$field][$value]);
          }
          else {
            $this->handler->tableAliases[$value] = $this->handler->tableAlias;
          }
          // Set tableAlias to the first of these.
          if (empty($this->handler->tableAlias)) {
            $this->handler->tableAlias = $this->handler->tableAliases[$value];
          }
        }
      }
      // Case 3: it's a 'not'.
      // We just do one join. We'll add a where clause during
      // the query phase to ensure that $table.$field IS NULL.
      else {
        $join = $this->getJoin();
        if ($join instanceof JoinPluginBase) {
          $join->type = 'LEFT';
          $join->extra = [];
          $join->extraOperator = 'OR';
          foreach ($this->handler->value as $value) {
            $join->extra[] = [
              'field' => $this->handler->realField,
              'value' => $value,
              'numeric' => !empty($this->handler->definition['numeric']),
            ];
          }

          $this->handler->tableAlias = $this->addTable($join);
        }
      }
    }
    return $this->handler->tableAlias;
  }

}
