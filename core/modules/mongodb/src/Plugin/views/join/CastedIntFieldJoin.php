<?php

namespace Drupal\mongodb\Plugin\views\join;

use Drupal\views\Plugin\views\join\CastedIntFieldJoin as CoreCastedIntFieldJoin;

/**
 * Overrides the views join plugin "casted_int_field_join".
 */
class CastedIntFieldJoin extends CoreCastedIntFieldJoin {

  use JoinPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function buildMongodbJoin($select_query, $table, $view_query) {
    if (empty($this->configuration['table formula'])) {
      $right_table = $this->table;
    }
    else {
      $right_table = $this->configuration['table formula'];
    }

    if ($this->leftTable) {
      $left_table = $view_query->getTableInfo($this->leftTable);
      $left_field = $this->leftFormula ?: "$left_table[alias].$this->leftField";
    }
    else {
      // This can be used if left_field is a formula or something. It should be
      // used only *very* rarely.
      $left_field = $this->leftField;
      $left_table = NULL;
    }

    $right_field = "{$table['alias']}.$this->field";

    assert(!isset($this->configuration['cast']) || in_array($this->configuration['cast'], ['right', 'left']));
    if (isset($this->configuration['cast']) && $this->configuration['cast'] === 'left') {
      $left_field = \Drupal::service('views.cast_sql')->getFieldAsInt($left_field);
    }
    else {
      $right_field = \Drupal::service('views.cast_sql')->getFieldAsInt($right_field);
    }

    $join_condition = $select_query->joinCondition()->compare($left_field, $right_field, $this->configuration['operator']);

    // Tack on the extra.
    if (isset($this->extra) && !empty($this->extra)) {
      $arguments = [];
      $this->joinAddExtra($arguments, $join_condition, $table, $select_query, $left_table);
    }
    else {
      $this->extra = [];
    }

    if (isset($this->extra) && is_array($this->extra)) {
      $substitutions = \Drupal::moduleHandler()->invokeAll('views_query_substitutions', [$view_query->view]);
      foreach ($this->extra as &$extra) {
        foreach ($extra as &$value) {
          foreach ($substitutions as $substitute_key => $substitute_value) {
            if ($value === $substitute_key) {
              $value = $substitute_value;
            }
          }
        }
      }
    }

    $select_query->addJoin($this->type, $right_table, $table['alias'], $join_condition);
  }

}
