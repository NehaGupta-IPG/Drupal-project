<?php

declare(strict_types=1);

namespace Drupal\path_alias_language_fallback_test\Hook;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for path_alias_language_fallback_test.
 */
class PathAliasLanguageFallbackTestHooks {

  /**
   * Implements hook_language_fallback_candidates_OPERATION_alter().
   */
  #[Hook('language_fallback_candidates_path_alias_alter')]
  public function language_fallback_candidates_path_alias_alter(array &$candidates, array $context): void {
    $fallback_language = \Drupal::state()
      ->get('path_alias_language_fallback_test.fallback_path_alias_alter.candidates');
    if ($fallback_language) {
      array_unshift($candidates, $fallback_language);
    }
  }

  /**
   * Implements hook_query_TAG_alter().
   */
  #[Hook('query_path_alias_language_fallback_alter')]
  public function query_path_alias_language_fallback_alter(AlterableInterface $query): void {

    /** @var \Drupal\Core\Database\Query\SelectInterface $query */
    // Replace the existing language code ordering.
    $fields = &$query->getOrderBy();
    unset($fields['base_table.langcode']);

    // Sort results according to the order of the language fallback chain, which
    // can be found in the values of the langcode condition.
    $conditions = &$query->conditions();
    foreach ($conditions as $condition) {
      if (is_array($condition) && isset($condition['field']) && $condition['field'] === 'base_table.langcode') {
        $order = '(CASE ';
        foreach (array_values($condition['value']) as $index => $lang) {
          $order .= "WHEN base_table.langcode = '$lang' THEN $index ";
        }
        $order .= 'END)';
        $order_alias = $query->addExpression($order, 'order');
        $fields = [$order_alias => 'ASC'] + $fields;
        break;
      }
    }
  }

}
