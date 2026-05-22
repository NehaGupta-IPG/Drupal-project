<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\search\SearchQuery as CoreSearchQuery;

/**
 * Search query extender and helper functions.
 *
 * Performs a query on the full-text search index for a word or words.
 *
 * This query is used by search plugins that use the search index (not all
 * search plugins do, as some use a different searching mechanism). It
 * assumes you have set up a query on the {search_index} table with alias 'i',
 * and will only work if the user is searching for at least one "positive"
 * keyword or phrase.
 *
 * For efficiency, users of this query can run the prepareAndNormalize()
 * method to figure out if there are any search results, before fully setting
 * up and calling execute() to execute the query. The scoring expressions are
 * not needed until the execute() step. However, it's not really necessary
 * to do this, because this class's execute() method does that anyway.
 *
 * During both the prepareAndNormalize() and execute() steps, there can be
 * problems. Call getStatus() to figure out if the query is OK or not.
 *
 * The query object is given the tag 'search_$type' and can be further
 * extended with hook_query_alter().
 */
class SearchQuery extends CoreSearchQuery {

  /**
   * Parses the search query into SQL conditions.
   *
   * Sets up the following variables:
   * - $this->keys;
   * - $this->words;
   * - $this->conditions;
   * - $this->simple;
   * - $this->matches.
   */
  protected function parseSearchExpression() {
    // Matches words optionally prefixed by a - sign. A word in this case is
    // something between two spaces, optionally quoted.
    preg_match_all('/ (-?)("[^"]+"|[^" ]+)/i', ' ' . $this->searchExpression, $keywords, PREG_SET_ORDER);

    if (count($keywords) == 0) {
      return;
    }

    // Classify tokens.
    $in_or = FALSE;
    $limit_combinations = \Drupal::config('search.settings')->get('and_or_limit');
    /** @var \Drupal\search\SearchTextProcessorInterface $text_processor */
    $text_processor = \Drupal::service('search.text_processor');
    // The first search expression does not count as AND.
    $and_count = -1;
    $or_count = 0;
    foreach ($keywords as $match) {
      if ($or_count && $and_count + $or_count >= $limit_combinations) {
        // Ignore all further search expressions to prevent Denial-of-Service
        // attacks using a high number of AND/OR combinations.
        $this->status |= SearchQuery::EXPRESSIONS_IGNORED;
        break;
      }

      // Strip off phrase quotes.
      $phrase = FALSE;
      if ($match[2][0] == '"') {
        $match[2] = substr($match[2], 1, -1);
        $phrase = TRUE;
        $this->simple = FALSE;
      }

      // Simplify keyword according to indexing rules and external
      // preprocessors. Use same process as during search indexing, so it
      // will match search index.
      $words = $text_processor->analyze($match[2]);
      // Re-explode in case simplification added more words, except when
      // matching a phrase.
      $words = $phrase ? [$words] : preg_split('/ /', $words, -1, PREG_SPLIT_NO_EMPTY);
      // Negative matches.
      if ($match[1] == '-') {
        $this->keys['negative'] = array_merge($this->keys['negative'], $words);
      }
      // OR operator: instead of a single keyword, we store an array of all
      // ORed keywords.
      elseif ($match[2] == 'OR' && count($this->keys['positive'])) {
        $last = array_pop($this->keys['positive']);
        // Starting a new OR?
        if (!is_array($last)) {
          $last = [$last];
        }
        $this->keys['positive'][] = $last;
        $in_or = TRUE;
        $or_count++;
        continue;
      }
      // AND operator: implied, so just ignore it.
      elseif ($match[2] == 'AND' || $match[2] == 'and') {
        continue;
      }

      // Plain keyword.
      else {
        if ($match[2] == 'or') {
          // Lower-case "or" instead of "OR" is a warning condition.
          $this->status |= SearchQuery::LOWER_CASE_OR;
        }
        if ($in_or) {
          // Add to last element (which is an array).
          $this->keys['positive'][count($this->keys['positive']) - 1] = array_merge($this->keys['positive'][count($this->keys['positive']) - 1], $words);
        }
        else {
          $this->keys['positive'] = array_merge($this->keys['positive'], $words);
          $and_count++;
        }
      }
      $in_or = FALSE;
    }

    if (!empty($this->keys['positive']) || !empty($this->keys['negative'])) {
      $this->unwindJoinAndAddFields('d', ['data']);
    }
    // The next line should not be needed, but it fixes the tests from
    // Drupal\Tests\search\Kernel\SearchMatchTest.
    $this->simple = FALSE;

    // Convert keywords into SQL statements.
    $has_and = FALSE;
    $has_or = FALSE;
    // Positive matches.
    foreach ($this->keys['positive'] as $key) {
      // Group of ORed terms.
      if (is_array($key) && count($key)) {
        // If we had already found one OR, this is another one ANDed with the
        // first, meaning it is not a simple query.
        if ($has_or) {
          $this->simple = FALSE;
        }
        $has_or = TRUE;
        $has_new_scores = FALSE;
        $query_or = $this->connection->condition('OR');
        foreach ($key as $or) {
          [$num_new_scores] = $this->parseWord($or);
          $has_new_scores |= $num_new_scores;
          $query_or->condition('data', "% $or %", 'LIKE');
        }
        if (count($query_or)) {
          $this->conditions->condition($query_or);
          // A group of OR keywords only needs to match once.
          $this->matches += ($has_new_scores > 0);
        }
      }
      // Single ANDed term.
      else {
        $has_and = TRUE;
        [$num_new_scores, $num_valid_words] = $this->parseWord($key);
        $this->conditions->condition('data', "% $key %", 'LIKE');
        if (!$num_valid_words) {
          $this->simple = FALSE;
        }
        // Each AND keyword needs to match at least once.
        $this->matches += $num_new_scores;
      }
    }
    if ($has_and && $has_or) {
      $this->simple = FALSE;
    }

    // Negative matches.
    foreach ($this->keys['negative'] as $key) {
      $this->conditions->condition('data', "% $key %", 'NOT LIKE');
      $this->simple = FALSE;
    }
  }

  /**
   * Prepares the query and calculates the normalization factor.
   *
   * After the query is normalized the keywords are weighted to give the results
   * a relevancy score. The query is ready for execution after this.
   *
   * Error and warning conditions can apply. Call getStatus() after calling
   * this method to retrieve them.
   *
   * @return bool
   *   TRUE if at least one keyword matched the search index; FALSE if not.
   */
  public function prepareAndNormalize() {
    $this->parseSearchExpression();
    $this->executedPrepare = TRUE;

    if (count($this->words) == 0) {
      // Although the query could proceed, there is no point in joining
      // with other tables and attempting to normalize if there are no
      // keywords present.
      $this->status |= SearchQuery::NO_POSITIVE_KEYWORDS;
      return FALSE;
    }

    // Build the basic search query: match the entered keywords.
    $or = $this->connection->condition('OR');
    foreach ($this->words as $word) {
      $or->condition('i.word', $word);
    }
    $this->condition($or);

    // Add keyword normalization information to the query.
    $this->join('search_total', 't', $this->joinCondition()->compare('i.word', 't.word'));
    $this
      ->condition('i.type', $this->type)
      ->groupBy('i.type')
      ->groupBy('i.sid');

    // If the query is simple, we should have calculated the number of
    // matching words we need to find, so impose that criterion. For non-
    // simple queries, this condition could lead to incorrectly deciding not
    // to continue with the full query.
    if ($this->simple) {
      $this->havingConditionWithType('COUNT', 'words_count', (int) $this->matches, '>=');
    }

    // Clone the query object to calculate normalization.
    $normalize_query = clone $this->query;

    // For complex search queries, add the LIKE conditions; if the query is
    // simple, we do not need them for normalization.
    if (!$this->simple) {
      $normalize_query->join('search_dataset', 'd',
        $normalize_query->joinCondition()
          ->compare('i.sid', 'd.sid')
          ->compare('i.type', 'd.type')
          ->compare('i.langcode', 'd.langcode')
      );
      if (count($this->conditions)) {
        $normalize_query->condition($this->conditions);
      }
    }

    // Calculate normalization, which is the max of all the search scores for
    // positive keywords in the query. And note that the query could have other
    // fields added to it by the user of this extension.
    $normalize_query->addSumMultiplyExpression('calculated_score', ['i.score', 't.count'], []);
    $result = $normalize_query
      ->range(0, 1)
      ->orderBy('calculated_score', 'DESC')
      ->execute()
      ->fetchObject();
    if (isset($result->i_sid)) {
      $result->sid = $result->i_sid;
      unset($result->i_sid);
    }
    if (isset($result->i_type)) {
      $result->type = $result->i_type;
      unset($result->i_type);
    }
    if (isset($result->i_langcode)) {
      $result->langcode = $result->i_langcode;
      unset($result->i_langcode);
    }
    if (isset($result->calculated_score)) {
      $this->normalize = (float) $result->calculated_score;
    }

    if ($this->normalize) {
      return TRUE;
    }

    // If the normalization value was zero, that indicates there were no
    // matches to the supplied positive keywords.
    $this->status |= SearchQuery::NO_KEYWORD_MATCHES;
    return FALSE;
  }

  /**
   * Executes the search.
   *
   * The complex conditions are applied to the query including score
   * expressions and ordering.
   *
   * Error and warning conditions can apply. Call getStatus() after calling
   * this method to retrieve them.
   *
   * @return \Drupal\Core\Database\StatementInterface|null
   *   A query result set containing the results of the query.
   */
  public function execute() {
    if (!$this->preExecute($this)) {
      return NULL;
    }

    // Add conditions to the query.
    $this->addJoin('INNER', 'search_dataset', 'd', $this->joinCondition('AND')
      ->compare('d.sid', 'i.sid')
      ->compare('d.type', 'i.type')
      ->compare('d.langcode', 'i.langcode')
    );
    if (count($this->conditions)) {
      $this->condition($this->conditions);
    }

    // Add default score (keyword relevance) if there are not any defined.
    if (empty($this->scores)) {
      $this->addScore('i.relevance');
    }

    if (count($this->multiply)) {
      // Re-normalize scores with multipliers by dividing by the total of all
      // multipliers. The expressions were altered in addScore(), so here just
      // add the arguments for the total.
      $sum = array_sum($this->multiply);
      for ($i = 0; $i < count($this->multiply); $i++) {
        $this->scoresArguments[':total_' . $i] = $sum;
      }
    }

    // Add arguments for the keyword relevance normalization number.
    $normalization = 1.0 / $this->normalize;
    for ($i = 0; $i < $this->relevance_count; $i++) {
      $this->scoresArguments[':normalization_' . $i] = $normalization;
    }

    // Add all scores together to form a query field.
    foreach ($this->scoresArguments as &$argument) {
      $argument = round($argument, 4);
    }
    $this->addSumMultiplyExpression('calculated_score', ['i.score', 't.count'], $this->scoresArguments);

    // If an order has not yet been set for this query, add a default order
    // that sorts by the calculated sum of scores.
    if (count($this->getOrderBy()) == 0) {
      $this->orderBy('calculated_score', 'DESC');
    }

    // Add query metadata.
    $this->addMetaData('normalize', $this->normalize);
    $this->addGroupField('sid', '_id.i_sid');
    $this->addGroupField('type', '_id.i_type');

    return $this->query->execute();
  }

  /**
   * Builds the default count query for SearchQuery.
   *
   * Since SearchQuery always uses GROUP BY, we can default to a subquery. We
   * also add the same conditions as execute() because countQuery() is called
   * first.
   */
  public function countQuery() {
    if (!$this->executedPrepare) {
      $this->prepareAndNormalize();
    }

    // Clone the inner query.
    $inner = clone $this->query;

    // Add conditions to query.
    $this->addJoin('INNER', 'search_dataset', 'd', $this->joinCondition('AND')
      ->compare('d.sid', 'i.sid')
      ->compare('d.type', 'i.type')
    );

    if (count($this->conditions)) {
      $inner->condition($this->conditions);
    }

    // Remove existing fields and expressions, they are not needed for a count
    // query.
    $fields =& $inner->getFields();
    $fields = [];
    $expressions =& $inner->getExpressions();
    $expressions = [];

    return $inner;
  }

}
