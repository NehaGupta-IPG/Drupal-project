<?php

namespace Drupal\mongodb\Plugin\Search;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Database\StatementInterface;
use Drupal\node\Plugin\Search\NodeSearch as CoreNodeSearch;
use Drupal\search\SearchQuery;

/**
 * Overriding the search plugin "node_search".
 */
class NodeSearch extends CoreNodeSearch {

  /**
   * {@inheritdoc}
   */
  protected function findResults() {
    $keys = $this->keywords;

    // Build matching conditions.
    $query = $this->database
      ->select('search_index', 'i', ['target' => 'replica'])
      ->extend(SearchQuery::class)
      ->extend(PagerSelectExtender::class);
    $query->addJoin('LEFT', 'node', 'n', $query->joinCondition()->compare('s.sid', 'n.nid')->compare('node_current_revision.langcode', 'n.langcode')->condition('node_current_revision.status', TRUE));
    $query->addTag('node_access')
      ->searchExpression($keys, $this->getPluginId());

    // Handle advanced search filters in the f query string.
    // \Drupal::request()->query->get('f') is an array that looks like this in
    // the URL: ?f[]=type:page&f[]=term:27&f[]=term:13&f[]=langcode:en
    // So $parameters['f'] looks like:
    // array('type:page', 'term:27', 'term:13', 'langcode:en');
    // We need to parse this out into query conditions, some of which go into
    // the keywords string, and some of which are separate conditions.
    $parameters = $this->getParameters();
    if (!empty($parameters['f']) && is_array($parameters['f'])) {
      $filters = [];
      // Match any query value that is an expected option and a value
      // separated by ':' like 'term:27'.
      $pattern = '/^(' . implode('|', array_keys($this->advanced)) . '):([^ ]*)/i';
      foreach ($parameters['f'] as $item) {
        if (preg_match($pattern, $item, $m)) {
          // Use the matched value as the array key to eliminate duplicates.
          $filters[$m[1]][$m[2]] = $m[2];
        }
      }

      // Now turn these into query conditions. This assumes that everything in
      // $filters is a known type of advanced search.
      foreach ($filters as $option => $matched) {
        $info = $this->advanced[$option];
        // Insert additional conditions. By default, all use the OR operator.
        $operator = empty($info['operator']) ? 'OR' : $info['operator'];
        $where = $this->databaseReplica->condition($operator);
        foreach ($matched as $value) {
          $where->condition($info['column'], $value);
        }
        $query->condition($where);
        if (!empty($info['join'])) {
          $query->join($info['join']['table'], $info['join']['alias'], $info['join']['condition']);
        }
      }
    }

    // Add the ranking expressions.
    $this->addNodeRankings($query);

    // Run the query.
    $find = $query
      // Add the language code of the indexed item to the result of the query,
      // since the node will be rendered using the respective language.
      // ->fields('i', ['langcode'])
      // And since SearchQuery makes these into GROUP BY queries, if we add
      // a field, for PostgreSQL we also need to make it an aggregate or a
      // GROUP BY. In this case, we want GROUP BY.
      ->groupBy('i.langcode')
      ->limit(10)
      ->execute();

    // Check query status and set messages if needed.
    $status = $query->getStatus();

    if ($status & SearchQuery::EXPRESSIONS_IGNORED) {
      $this->messenger->addWarning($this->t('Your search used too many AND/OR expressions. Only the first @count terms were included in this search.', ['@count' => $this->searchSettings->get('and_or_limit')]));
    }

    if ($status & SearchQuery::LOWER_CASE_OR) {
      $this->messenger->addWarning($this->t('Search for either of the two terms with uppercase <strong>OR</strong>. For example, <strong>cats OR dogs</strong>.'));
    }

    if ($status & SearchQuery::NO_POSITIVE_KEYWORDS) {
      $this->messenger->addWarning($this->formatPlural($this->searchSettings->get('index.minimum_word_size'), 'You must include at least one keyword to match in the content, and punctuation is ignored.', 'You must include at least one keyword to match in the content. Keywords must be at least @count characters, and punctuation is ignored.'));
    }

    return $find;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareResults(StatementInterface $found) {
    $results = [];

    $node_storage = $this->entityTypeManager->getStorage('node');
    $node_render = $this->entityTypeManager->getViewBuilder('node');
    $keys = $this->keywords;

    foreach ($found as $item) {
      // Move the results from the MongoDB variables to the ones expected by
      // Drupal.
      if (isset($item->i_sid)) {
        $item->sid = $item->i_sid;
        unset($item->i_sid);
      }
      if (isset($item->i_type)) {
        $item->type = $item->i_type;
        unset($item->i_type);
      }
      if (isset($item->i_langcode)) {
        $item->langcode = $item->i_langcode;
        unset($item->i_langcode);
      }

      // Render the node.
      /** @var \Drupal\node\NodeInterface $node */
      $node = $node_storage->load($item->sid)->getTranslation($item->langcode);
      $build = $node_render->view($node, 'search_result', $item->langcode);

      /** @var \Drupal\node\NodeTypeInterface $type*/
      $type = $this->entityTypeManager->getStorage('node_type')->load($node->bundle());

      unset($build['#theme']);
      $build['#pre_render'][] = [$this, 'removeSubmittedInfo'];

      // Fetch comments for snippet.
      $rendered = $this->renderer->renderInIsolation($build);
      $this->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
      $rendered .= ' ' . $this->moduleHandler->invoke('comment', 'node_update_index', [$node]);

      $extra = $this->moduleHandler->invokeAll('node_search_result', [$node]);

      $username = [
        '#theme' => 'username',
        '#account' => $node->getOwner(),
      ];

      $result = [
        'link' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
        'type' => $type->label(),
        'title' => $node->label(),
        'node' => $node,
        'extra' => $extra,
        'score' => $item->calculated_score,
        'snippet' => search_excerpt($keys, $rendered, $item->langcode),
        'langcode' => $node->language()->getId(),
      ];

      $this->addCacheableDependency($node);

      // We have to separately add the node owner's cache tags because search
      // module doesn't use the rendering system, it does its own rendering
      // without taking cacheability metadata into account. So we have to do it
      // explicitly here.
      $this->addCacheableDependency($node->getOwner());

      if ($type->displaySubmitted()) {
        $result += [
          'user' => $this->renderer->renderInIsolation($username),
          'date' => $node->getChangedTime(),
        ];
      }

      $results[] = $result;

    }
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex() {
    // Interpret the cron limit setting as the maximum number of nodes to index
    // per cron run.
    $limit = (int) $this->searchSettings->get('index.cron_limit');

    $query = $this->database->select('node', 'n', ['target' => 'replica']);
    $query->fields('n', ['nid']);
    $query->addJoin('LEFT', 'search_dataset', 'sd', $query->joinCondition()->compare('n.nid', 'sd.sid')->condition('sd.type', $this->getPluginId()));
    $query->fields('sd', ['sid', 'reindex']);
    $query->condition(
      $query->orConditionGroup()
        ->isNull('sd.sid')
        ->condition('sd.reindex', 0, '<>')
    );
    $query->orderBy('sd.reindex', 'DESC')
      ->orderBy('nid')
      ->range(0, $limit);

    $result = $query->execute()->fetchAll();
    $nids = [];
    foreach ($result as $row) {
      $nids[] = $row->nid;
    }

    if (!$nids) {
      return;
    }

    $node_storage = $this->entityTypeManager->getStorage('node');
    $words = [];
    try {
      foreach ($node_storage->loadMultiple($nids) as $node) {
        $words += $this->indexNode($node);
      }
    }
    finally {
      $this->searchIndex->updateWordWeights($words);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function indexStatus() {
    $total = $this->database->select('node')
      ->countQuery()
      ->execute()
      ->fetchField();
    $query = $this->database->select('node', 'n')
      ->fields('n', ['nid']);
    $query->addJoin('LEFT', 'search_dataset', 'sd', $query->joinCondition()->compare('n.nid', 'sd.sid')->condition('sd.type', $this->getPluginId()));
    $condition = $this->database->condition('OR');
    $condition->condition('sd.sid', NULL, 'IS NULL');
    $condition->condition('sd.reindex', 0, '<>');
    $result = $query->condition($condition)
      ->execute()
      ->fetchAll();

    $nids = [];
    foreach ($result as $node) {
      if (isset($node->nid) && !in_array($node->nid, $nids)) {
        $nids[] = $node->nid;
      }
    }
    $remaining = count($nids);

    return ['remaining' => $remaining, 'total' => $total];
  }

}
