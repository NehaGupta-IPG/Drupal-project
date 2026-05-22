<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\node\Plugin\views\filter\Access as CoreAccess;

/**
 * Overriding the views filter plugin "node_access".
 */
class Access extends CoreAccess {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $account = $this->view->getUser();
    if (!$account->hasPermission('bypass node access')) {
      $table = $this->ensureMyTable();
      $grants = $this->query->getConnection()->condition('OR');
      foreach (node_access_grants('view', $account) as $realm => $gids) {
        foreach ($gids as $gid) {
          $grants->condition(($this->query->getConnection()->condition('AND'))
            ->condition($table . '.gid', (int) $gid)
            ->condition($table . '.realm', $realm)
          );
        }
      }

      $this->query->addCondition('AND', $grants);
      $this->query->addCondition('AND', $table . '.grant_view', TRUE);
    }
  }

}
