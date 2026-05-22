<?php

namespace Drupal\mongodb\Plugin\views\join;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\views\Plugin\views\join\FieldOrLanguageJoin as CoreFieldOrLanguageJoin;

/**
 * Overrides the views join plugin "field_or_language_join".
 */
class FieldOrLanguageJoin extends CoreFieldOrLanguageJoin {

  use JoinPluginTrait;

  /**
   * {@inheritdoc}
   */
  protected function joinAddExtra(&$arguments, &$condition, $table, SelectInterface $select_query, $left_table = NULL) {
    if (empty($this->extra)) {
      return;
    }

    if (is_array($this->extra)) {
      $extras = [];
      foreach ($this->extra as $extra) {
        $extras[] = $this->buildExtra($extra, $arguments, $table, $select_query, $left_table, is_string($condition));
      }

      // Remove and store the langcode OR bundle join condition extra.
      $language_bundle_conditions = [];
      foreach ($extras as $key => $extra) {
        if (is_string($extra)) {
          if (str_contains($extra, '.langcode') || str_contains($extra, '.bundle')) {
            $language_bundle_conditions[] = $extra;
            unset($extras[$key]);
          }
        }
        else {
          if (str_contains($extra['field'], '.langcode')
            || str_contains($extra['field'], '.bundle')
            || (isset($extra['field2']) && str_contains($extra['field2'], '.langcode'))
            || (isset($extra['field2']) && str_contains($extra['field2'], '.bundle'))
          ) {
            $language_bundle_conditions[] = $extra;
            unset($extras[$key]);
          }
        }
      }

      // Start of BC layer.
      if (Inspector::assertAllStrings($extras)) {
        if (count($extras) > 1) {
          $condition .= ' AND (' . implode(' ' . $this->extraOperator . ' ', $extras) . ')';
        }
        elseif ($extras) {
          $condition .= ' AND ' . array_shift($extras);
        }
      }
      else {
        // End of BC layer.
        foreach ($extras as $extra) {
          if (isset($extra['field2'])) {
            $condition->compare($extra['field'], $extra['field2'], $extra['operator']);
          }
          else {
            $condition->condition($extra['field'], $extra['value'], $extra['operator']);
          }
        }
      }

      // Tack on the langcode OR bundle join condition extra.
      if (!empty($language_bundle_conditions)) {
        // Start of BC layer.
        if (Inspector::assertAllStrings($language_bundle_conditions)) {
          $condition .= ' AND (' . implode(' OR ', $language_bundle_conditions) . ')';
        }
        else {
          // End of BC layer.
          $inner_condition = $select_query->getConnection()->condition('OR');
          foreach ($language_bundle_conditions as $language_bundle_condition) {
            if (isset($language_bundle_condition['field2'])) {
              $inner_condition->compare($language_bundle_condition['field'], $language_bundle_condition['field2'], $language_bundle_condition['operator']);
            }
            else {
              $inner_condition->condition($language_bundle_condition['field'], $language_bundle_condition['value'], $language_bundle_condition['operator']);
            }
          }
          $condition->condition($inner_condition);
        }
      }
    }
    elseif (is_string($this->extra)) {
      $condition .= " AND ($this->extra)";
    }
  }

}
