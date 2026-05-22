<?php

namespace Drupal\mongodb\Hook;

use Drupal\views\ViewExecutable;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for MongoDB.
 */
class MongodbViewsExecutionHooks {

  /**
   * Implements hook_views_query_substitutions().
   *
   * Allow replacement of current user ID so we can cache these queries.
   */
  #[Hook('views_query_substitutions', module: 'user')]
  public function viewsQuerySubstitutions(ViewExecutable $view): array {
    return ['***CURRENT_USER***' => (int) \Drupal::currentUser()->id()];
  }

}
