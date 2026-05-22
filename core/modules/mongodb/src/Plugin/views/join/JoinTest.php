<?php

namespace Drupal\mongodb\Plugin\views\join;

use Drupal\views_test_data\Plugin\views\join\JoinTest as CoreJoinTest;

/**
 * Overrides the views join plugin "join_test".
 */
class JoinTest extends CoreJoinTest {

  use JoinPluginTrait;

  /**
   * A value which is used to build an additional join condition.
   *
   * @var int
   */
  protected $joinValue;

  /**
   * Returns the joinValue property.
   *
   * @return int
   *   The join value.
   */
  public function getJoinValue() {
    return $this->joinValue;
  }

  /**
   * Sets the joinValue property.
   *
   * @param int $join_value
   *   The join value.
   */
  public function setJoinValue($join_value) {
    $this->joinValue = $join_value;
  }

  /**
   * The temporary join build method for MongoDB.
   */
  public function buildMongodbJoin($select_query, $table, $view_query) {
    // Add an additional hardcoded condition to the query.
    $this->extra = [
      [
        'left_field' => 'uid',
        'value' => $this->getJoinValue(),
        'operator' => '=',
      ],
    ];

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
    $condition = "$left_field = $table[alias].$this->field";
    $arguments = [];

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

    // Tack on the extra.
    if (isset($this->extra) && !empty($this->extra)) {
      $this->joinAddExtra($arguments, $condition, $table, $select_query, $left_table);
    }
    else {
      $this->extra = [];
    }

    $join_condition = $select_query->joinCondition()->compare($table['alias'] . '.' . $right_field, $left_table['alias'] . '.' . $left_field);
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
        $join_condition->condition($left_table['alias'] . '.' . $extra['left_field'], $extra['value'], $extra['operator'] ?? '=');
      }
    }

    $select_query->addJoin($this->type, $right_table, $table['alias'], $join_condition);
    if (isset($this->configuration['one_to_many']) && $this->configuration['one_to_many']) {
      $select_query->addFilterUnwindPath($table['alias']);
    }
  }

}
