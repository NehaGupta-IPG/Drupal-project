<?php

namespace Drupal\mongodb\Plugin\views\join;

/**
 * Trait for overriding the JoinPluginBase class.
 */
trait JoinPluginTrait {

  /**
   * Whether to join to the all revisions embedded table or do a regular join.
   *
   * @var string
   */
  public $revisionableJoin;

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
    }
    else {
      // This can be used if left_field is a formula or something. It should be
      // used only *very* rarely.
      $left_table = NULL;
    }

    $right_field = $this->field;
    $left_field = $this->leftField ?? NULL;

    if (!empty($view_query->getCurrentRevisionTable()) && !empty($view_query->getAllRevisionsTable()) && !empty($view_query->getLatestRevisionTable())) {
      $search_needle = $view_query->getCurrentRevisionTable() . '.';
      if ($view_query->hasLatestRevisionFilter()) {
        $replace_needle = $view_query->getLatestRevisionTable() . '.';
      }
      else {
        $replace_needle = $view_query->getAllRevisionsTable() . '.';
      }
      if (strpos($left_field, $search_needle) !== FALSE) {
        $left_field = str_replace($search_needle, $replace_needle, $left_field);
      }
    }

    if (isset($left_table['alias'])) {
      $operator = $this->configuration['operator'] ?? '=';
      $join_condition = $select_query->joinCondition()->compare($left_table['alias'] . '.' . $left_field, $table['alias'] . '.' . $right_field, $operator);
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

    // Tack on the extra.
    if (isset($this->extra) && !empty($this->extra) && isset($join_condition)) {
      $arguments = [];
      $this->joinAddExtra($arguments, $join_condition, $table, $select_query, $left_table);
    }
    else {
      $this->extra = [];
    }

    if (isset($join_condition)) {
      $select_query->addJoin($this->type, $right_table, $table['alias'], $join_condition);
      if (isset($this->configuration['one_to_many']) && $this->configuration['one_to_many']) {
        $select_query->addFilterUnwindPath($table['alias']);
      }
    }
  }

}
