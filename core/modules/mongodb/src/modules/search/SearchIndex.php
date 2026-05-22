<?php

namespace Drupal\mongodb\modules\search;

use Drupal\search\Exception\SearchIndexException;
use Drupal\search\SearchIndex as CoreSearchIndex;

// cspell:ignore accum tagstack tagwords

/**
 * Provides search index management functions.
 */
class SearchIndex extends CoreSearchIndex {

  /**
   * {@inheritdoc}
   */
  public function index($type, $sid, $langcode, $text, $update_weights = TRUE) {
    $settings = $this->configFactory->get('search.settings');
    $minimum_word_size = $settings->get('index.minimum_word_size');

    // Keep track of the words that need to have their weights updated.
    $current_words = [];

    // Multipliers for scores of words inside certain HTML tags. The weights are
    // stored in config so that modules can overwrite the default weights.
    // Note: 'a' must be included for link ranking to work.
    $tags = $settings->get('index.tag_weights');

    // Strip off all ignored tags to speed up processing, but insert space
    // before and after them to keep word boundaries.
    $text = str_replace(['<', '>'], [' <', '> '], $text);
    $text = strip_tags($text, '<' . implode('><', array_keys($tags)) . '>');

    // Split HTML tags from plain text.
    $split = preg_split('/\s*<([^>]+?)>\s*/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    // Note: PHP ensures the array consists of alternating delimiters and
    // literals and begins and ends with a literal (inserting $null as
    // required).
    // Odd/even counter. Tag or no tag.
    $tag = FALSE;
    // Starting score per word.
    $score = 1;
    // Accumulator for cleaned up data.
    $accumulator = ' ';
    // Stack with open tags.
    $tag_stack = [];
    // Counter for consecutive words.
    $tag_words = 0;
    // Focus state.
    $focus = 1;

    // Accumulator for words for index.
    $scored_words = [];

    foreach ($split as $value) {
      if ($tag) {
        // Increase or decrease score per word based on tag.
        [$tagname] = explode(' ', $value, 2);
        $tagname = mb_strtolower($tagname);
        // Closing or opening tag?
        if ($tagname[0] == '/') {
          $tagname = substr($tagname, 1);
          // If we encounter unexpected tags, reset score to avoid incorrect
          // boosting.
          if (!count($tag_stack) || $tag_stack[0] != $tagname) {
            $tag_stack = [];
            $score = 1;
          }
          else {
            // Remove from tag stack and decrement score.
            $score = max(1, $score - $tags[array_shift($tag_stack)]);
          }
        }
        else {
          if (isset($tag_stack[0]) && $tag_stack[0] == $tagname) {
            // None of the tags we look for make sense when nested identically.
            // If they are, it's probably broken HTML.
            $tag_stack = [];
            $score = 1;
          }
          else {
            // Add to open tag stack and increment score.
            array_unshift($tag_stack, $tagname);
            $score += $tags[$tagname];
          }
        }
        // A tag change occurred, reset counter.
        $tag_words = 0;
      }
      else {
        // Note: use of PREG_SPLIT_DELIM_CAPTURE above will introduce empty
        // values.
        if ($value != '') {
          $words = $this->textProcessor->process($value, $langcode);
          foreach ($words as $word) {
            // Add word to accumulator.
            $accumulator .= $word . ' ';
            // Check word length.
            if (is_numeric($word) || mb_strlen($word) >= $minimum_word_size) {
              if (!isset($scored_words[$word])) {
                $scored_words[$word] = 0;
              }
              $scored_words[$word] += $score * $focus;
              // Focus is a decaying value in terms of the amount of unique
              // words up to this point. From 100 words and more, it decays, to
              // e.g. 0.5 at 500 words and 0.3 at 1000 words.
              $focus = min(1, .01 + 3.5 / (2 + count($scored_words) * .015));
            }
            $tag_words++;
            // Too many words inside a single tag probably mean a tag was
            // accidentally left open.
            if (count($tag_stack) && $tag_words >= 15) {
              $tag_stack = [];
              $score = 1;
            }
          }
        }
      }
      $tag = !$tag;
    }

    // Remove the item $sid from the search index, and invalidate the relevant
    // cache tags.
    $this->clear($type, $sid, $langcode);

    try {
      // Insert cleaned up data into dataset.
      $this->connection->insert('search_dataset')
        ->fields([
          'sid' => $sid,
          'langcode' => $langcode,
          'type' => $type,
          'data' => $accumulator,
          'reindex' => 0,
        ])
        ->execute();

      // Insert results into search index.
      foreach ($scored_words as $word => $score) {
        // If a word already exists in the database, its score gets increased
        // appropriately. If not, we create a new record with the appropriate
        // starting score.
        $index = $this->connection->select('search_index')
          ->fields('search_index')
          ->condition('word', $word)
          ->condition('sid', (int) $sid)
          ->condition('langcode', $langcode)
          ->condition('type', $type)
          ->execute()
          ->fetchObject();

        if ($index) {
          $this->connection->update('search_index')
            ->fields([
              'score' => $index->score + $score,
            ])
            ->condition('word', $word)
            ->condition('sid', (int) $sid)
            ->condition('langcode', $langcode)
            ->condition('type', $type)
            ->execute();
        }
        else {
          $this->connection->insert('search_index')
            ->fields([
              'word' => $word,
              'sid' => $sid,
              'langcode' => $langcode,
              'type' => $type,
              'score' => $score,
            ])
            ->execute();
        }

        $current_words[$word] = TRUE;
      }
    }
    catch (\Exception $e) {
      throw new SearchIndexException("Failed to insert dataset in index for type '$type', sid '$sid' and langcode '$langcode'", 0, $e);
    }
    finally {
      if ($update_weights) {
        $this->updateWordWeights($current_words);
      }
    }
    return $current_words;
  }

  /**
   * {@inheritdoc}
   */
  public function updateWordWeights(array $words) {
    try {
      // Update word IDF (Inverse Document Frequency) counts for new/changed
      // words.
      $words = array_keys($words);
      foreach ($words as $word) {
        // Get total count.
        $results = $this->replica->select('search_index')
          ->fields('search_index', ['score'])
          ->condition('word', $word)
          ->execute()
          ->fetchAll();
        $total = 0;
        foreach ($results as $result) {
          $total += $result->score;
        }
        // Apply Zipf's law to equalize the probability distribution.
        $total = log10(1 + 1 / (max(1, $total)));
        $this->connection->merge('search_total')
          ->key('word', $word)
          ->fields(['count' => $total])
          ->execute();
      }
      // Find words that were deleted from search_index, but are still in
      // search_total. We use a LEFT JOIN between the two tables and keep only
      // the rows which fail to join.
      $query = $this->replica->select('search_total', 't');
      $query->addField('t', 'word', 'realword');
      $query->addJoin('LEFT', 'search_index', 'i', $query->joinCondition()->compare('t.word', 'i.word'));
      $result = $query->isNull('i.word')
        ->execute()
        ->fetchAll();
      $or = $this->replica->condition('OR');
      foreach ($result as $word) {
        $or->condition('word', $word->realword);
      }
      if (count($or) > 0) {
        $this->connection->delete('search_total')
          ->condition($or)
          ->execute();
      }
    }
    catch (\Exception $e) {
      throw new SearchIndexException("Failed to update totals for index words.", 0, $e);
    }
  }

}
