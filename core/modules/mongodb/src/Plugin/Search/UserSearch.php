<?php

namespace Drupal\mongodb\Plugin\Search;

use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\user\Plugin\Search\UserSearch as CoreUserSearch;

/**
 * Overriding the search plugin "user_search".
 */
class UserSearch extends CoreUserSearch {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $results = [];
    if (!$this->isSearchExecutable()) {
      return $results;
    }

    // Process the keywords.
    $keys = $this->keywords;
    // Escape for LIKE matching.
    $keys = $this->database->escapeLike($keys);
    // Replace wildcards with MySQL/PostgreSQL wildcards.
    $keys = preg_replace('!\*+!', '%', $keys);

    // Run the query to find matching users.
    $query = $this->database
      ->select('users', 'users')
      ->extend(PagerSelectExtender::class);
    $query->fields('users', ['uid']);
    $query->condition('user_translations.default_langcode', TRUE);
    if ($this->currentUser->hasPermission('administer users')) {
      // Administrators can also search in the otherwise private email field,
      // and they don't need to be restricted to only active users.
      $query->fields('users', ['mail']);
      $query->condition($query->orConditionGroup()
        ->condition('user_translations.name', '%' . $keys . '%', 'LIKE')
        ->condition('user_translations.mail', '%' . $keys . '%', 'LIKE')
      );
    }
    else {
      // Regular users can only search via usernames, and we do not show them
      // blocked accounts.
      $query->condition('user_translations.name', '%' . $keys . '%', 'LIKE')
        ->condition('user_translations.status', TRUE);
    }
    $uids = $query
      ->limit(15)
      ->execute()
      ->fetchCol();
    $accounts = $this->entityTypeManager->getStorage('user')->loadMultiple($uids);

    foreach ($accounts as $account) {
      $result = [
        'title' => $account->getDisplayName(),
        'link' => $account->toUrl('canonical', ['absolute' => TRUE])->toString(),
      ];
      if ($this->currentUser->hasPermission('administer users')) {
        $result['title'] .= ' (' . $account->getEmail() . ')';
      }
      $this->addCacheableDependency($account);
      $results[] = $result;
    }

    return $results;
  }

}
