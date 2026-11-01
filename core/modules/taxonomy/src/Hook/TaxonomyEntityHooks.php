<?php

namespace Drupal\taxonomy\Hook;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * Entity hooks for taxonomy module.
 */
class TaxonomyEntityHooks {

  public function __construct(
    protected AccountInterface $currentUser,
  ) {

  }

  /**
   * Implements hook_query_TAG_alter().
   */
  #[Hook('query_taxonomy_term_access_alter')]
  function queryTaxonomyTermAccessAlter(AlterableInterface $query): void {

    if (!$query instanceof SelectInterface) {
      return;
    }

    // Read meta-data from query, if provided.
    $account = $query->getMetaData('account') ?: $this->currentUser;
    if ($account->hasPermission('administer taxonomy')) {
      return;
    }

    // No terms are accessible without access content permission.
    if (!$account->hasPermission('access content')) {
      $query->addExpression('1 = 0');
      return;
    }

    $tables = $query->getTables();

    $base_alias = NULL;
    $data_alias = $query->getMetaData('access_alias');

    // If the table to filter on is not explicitly provided, try to find it
    // prefer non-revision tables.
    if (!$data_alias) {
      foreach ($tables as $table) {
        if ($table['table'] === 'taxonomy_term_data') {
          $base_alias = $table['alias'];
        }
        elseif (!$base_alias && $table['table'] === 'taxonomy_term_revision') {
          $base_alias = $table['alias'];
        }
        elseif ($table['table'] === 'taxonomy_term_field_data') {
          $data_alias = $table['alias'];
        }
        elseif (!$data_alias && $table['table'] === 'taxonomy_term_field_revision') {
          $data_alias = $table['alias'];
        }
      }
    }

    if ($base_alias && !$data_alias) {
      $data_alias = $query->innerJoin('taxonomy_term_field_data', 'data_table', $base_alias . '.tid = %alias.tid AND %alias.default_langcode = 1');
    }

    if ($data_alias) {
      $query->condition($data_alias . '.status', 1);
    }

  }

}
