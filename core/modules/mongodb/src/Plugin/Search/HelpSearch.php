<?php

namespace Drupal\mongodb\Plugin\Search;

use Drupal\Core\Language\LanguageInterface;
use Drupal\help\Plugin\Search\HelpSearch as CoreHelpSearch;

/**
 * Overriding the search plugin "help_search".
 */
class HelpSearch extends CoreHelpSearch {

  /**
   * {@inheritdoc}
   */
  public function updateIndex() {
    // Update the list of items to be indexed.
    $this->updateTopicList();

    // Find some items that need to be updated. Start with ones that have
    // never been indexed.
    $limit = (int) $this->searchSettings->get('index.cron_limit');

    $query = $this->database->select('help_search_items', 'hsi');
    $query->fields('hsi', ['sid', 'section_plugin_id', 'topic_id']);
    $query->leftJoin('search_dataset', 'sd',
      $query->joinCondition()
        ->compare('sd.sid', 'hsi.sid')
        ->condition('sd.type', $this->getType())
    );
    $query->isNull('sd.sid');
    $results = $query->execute()->fetchAll();

    $grouped_items = [];
    foreach ($results as $row) {
      $grouped_items[$row->sid][$row->section_plugin_id][$row->topic_id] = 1;
    }

    // Order the grouped items by the sid.
    ksort($grouped_items);

    $items = [];
    foreach ($grouped_items as $grouped_sid => $grouped_item) {
      foreach ($grouped_item as $grouped_section_plugin_id => $grouped_inner_item) {
        foreach (array_keys($grouped_inner_item) as $grouped_topic_id) {
          $items[] = (object) [
            'sid' => $grouped_sid,
            'section_plugin_id' => $grouped_section_plugin_id,
            'topic_id' => $grouped_topic_id,
          ];
        }
      }
    }

    if (count($items) > $limit) {
      $items = array_slice($items, 0, $limit);
    }

    // If there is still space in the indexing limit, index items that have
    // been indexed before, but are currently marked as needing a re-index.
    if (count($items) < $limit) {
      $query = $this->database->select('help_search_items', 'hsi');
      $query->fields('hsi', ['sid', 'section_plugin_id', 'topic_id']);
      $query->leftJoin('search_dataset', 'sd',
        $query->joinCondition()
          ->compare('sd.sid', 'hsi.sid')
          ->condition('sd.type', $this->getType())
      );
      $query->condition('sd.reindex', 0, '<>');
      $results = $query->execute()->fetchAll();

      $grouped_items = [];
      foreach ($results as $row) {
        $grouped_items[$row->sid][$row->section_plugin_id][$row->topic_id] = 1;
      }

      // Order the grouped items by the sid.
      ksort($grouped_items);

      foreach ($grouped_items as $grouped_sid => $grouped_item) {
        foreach ($grouped_item as $grouped_section_plugin_id => $grouped_inner_item) {
          foreach (array_keys($grouped_inner_item) as $grouped_topic_id) {
            $items[] = (object) [
              'sid' => $grouped_sid,
              'section_plugin_id' => $grouped_section_plugin_id,
              'topic_id' => $grouped_topic_id,
            ];
          }
        }
      }

      if (count($items) > $limit) {
        $items = array_slice($items, 0, $limit);
      }
    }

    // Index the items we have chosen, in all available languages.
    $language_list = $this->languageManager->getLanguages(LanguageInterface::STATE_CONFIGURABLE);
    $section_plugins = [];

    $words = [];
    try {
      foreach ($items as $item) {
        $section_plugin_id = $item->section_plugin_id;
        if (!isset($section_plugins[$section_plugin_id])) {
          $section_plugins[$section_plugin_id] = $this->getSectionPlugin($section_plugin_id);
        }

        if (!$section_plugins[$section_plugin_id]) {
          $this->removeItemsFromIndex($item->sid);
          continue;
        }

        $section_plugin = $section_plugins[$section_plugin_id];
        $this->searchIndex->clear($this->getType(), $item->sid);
        foreach ($language_list as $langcode => $language) {
          $topic = $section_plugin->renderTopicForSearch($item->topic_id, $language);
          if ($topic) {
            // Index the title plus body text.
            $text = '<h1>' . $topic['title'] . '</h1>' . "\n" . $topic['text'];
            $words += $this->searchIndex->index($this->getType(), $item->sid, $langcode, $text, FALSE);
          }
        }
      }
    }
    finally {
      $this->searchIndex->updateWordWeights($words);
      $this->updateIndexState();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function indexStatus() {
    $this->updateTopicList();
    $total = $this->database->select('help_search_items', 'hsi')
      ->countQuery()
      ->execute()
      ->fetchField();

    $query = $this->database->select('help_search_items', 'hsi');
    $query->fields('hsi', ['sid']);
    $query->leftJoin('search_dataset', 'sd',
      $query->joinCondition()
        ->compare('hsi.sid', 'sd.sid')
        ->condition('sd.type', $this->getType())
    );
    $condition = $this->database->condition('OR');
    $condition->condition('sd.reindex', 0, '<>')
      ->isNull('sd.sid');
    $query->condition($condition);
    $results = $query->execute()->fetchCol();

    // Get the distinct sids.
    $distinct_sids = [];
    foreach ($results as $sid) {
      if (!isset($distinct_sids[$sid])) {
        $distinct_sids[$sid] = 1;
      }
    }

    return [
      'remaining' => count($distinct_sids),
      'total' => $total,
    ];
  }

}
