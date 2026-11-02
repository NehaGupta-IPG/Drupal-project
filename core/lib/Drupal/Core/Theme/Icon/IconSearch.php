<?php

declare(strict_types=1);

namespace Drupal\Core\Theme\Icon;

use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;

/**
 * Service for searching icons.
 *
 * Matches are ranked in six buckets, best first:
 * 1. The full icon id or its icon part equals the query.
 * 2. The icon name starts with the query.
 * 3. Every query word is a whole word of the icon name, in query order.
 * 4. Every query word is a whole word of the icon name, in any order.
 * 5. The query is a substring of the icon id, or every query word is a
 *    substring, in query order.
 * 6. Every query word is a substring of the icon id, in any order.
 *
 * Within a bucket the order the icon packs declare their icons is kept.
 *
 * Results are not cached here: every distinct query would add a cache entry,
 * and the route exposing the search accepts anonymous requests. The icon
 * definitions are cached by the icon pack manager.
 *
 * @internal
 *   This API is experimental.
 */
class IconSearch implements IconSearchInterface {

  /**
   * Icon preview size in pixels.
   */
  public const int ICON_PREVIEW_SIZE = 32;

  /**
   * Constructs an IconSearch object.
   *
   * @param \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface $pluginManagerIconPack
   *   The icon pack manager service.
   */
  public function __construct(
    private readonly IconPackManagerInterface $pluginManagerIconPack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function search(
    string $query,
    array $allowed_icon_pack = [],
    int $max_result = self::SEARCH_RESULT,
  ): array {
    $query = trim($query);

    // Return empty if query is too short.
    if (mb_strlen($query) < self::SEARCH_MIN_LENGTH || $max_result < 1) {
      return [];
    }

    return array_slice($this->doSearch($query, $allowed_icon_pack), 0, $max_result);
  }

  /**
   * Performs the actual icon search.
   *
   * @param string $query
   *   The search query string.
   * @param array $allowed_icon_pack
   *   Array of allowed icon pack IDs.
   *
   * @return string[]
   *   All matching full icon ids, best match first.
   */
  private function doSearch(string $query, array $allowed_icon_pack): array {
    $query_lower = mb_strtolower($query);
    $query_words = preg_split('/\s+/', $query_lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];

    // One bucket per priority level, see the class documentation.
    $buckets = [[], [], [], [], [], []];

    foreach (array_keys($this->pluginManagerIconPack->getIcons($allowed_icon_pack)) as $icon_full_id) {
      $id_lower = mb_strtolower((string) $icon_full_id);
      $separator = mb_strpos($id_lower, ':');
      $name_lower = $separator === FALSE ? $id_lower : mb_substr($id_lower, $separator + 1);
      // Whole word buckets only look at the icon name so that a pack id never
      // outranks icons actually named after the query.
      $name_words = preg_split('/[-_\s]+/', $name_lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];

      if ($id_lower === $query_lower || $name_lower === $query_lower) {
        $buckets[0][] = $icon_full_id;
      }
      elseif (str_starts_with($name_lower, $query_lower)) {
        $buckets[1][] = $icon_full_id;
      }
      elseif ($this->wordsInOrder($name_words, $query_words)) {
        $buckets[2][] = $icon_full_id;
      }
      elseif (!array_diff($query_words, $name_words)) {
        $buckets[3][] = $icon_full_id;
      }
      elseif (str_contains($id_lower, $query_lower) || $this->substringsInOrder($id_lower, $query_words)) {
        $buckets[4][] = $icon_full_id;
      }
      elseif ($this->containsAllWords($id_lower, $query_words)) {
        $buckets[5][] = $icon_full_id;
      }
    }

    return array_merge(...$buckets);
  }

  /**
   * Checks that every query word is a whole icon name word, in query order.
   *
   * @param string[] $name_words
   *   The words the icon name is made of.
   * @param string[] $query_words
   *   The lowercased query words.
   *
   * @return bool
   *   TRUE when all query words appear as whole words in query order.
   */
  private function wordsInOrder(array $name_words, array $query_words): bool {
    if ($query_words === []) {
      return FALSE;
    }

    $index = 0;
    foreach ($name_words as $name_word) {
      if ($name_word === $query_words[$index]) {
        $index++;
        if ($index === \count($query_words)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Checks that every query word is a substring of the icon id, in order.
   *
   * @param string $id_lower
   *   The lowercased full icon id.
   * @param string[] $query_words
   *   The lowercased query words.
   *
   * @return bool
   *   TRUE when all query words appear as substrings in query order.
   */
  private function substringsInOrder(string $id_lower, array $query_words): bool {
    if ($query_words === []) {
      return FALSE;
    }

    $offset = 0;
    foreach ($query_words as $word) {
      $position = mb_strpos($id_lower, $word, $offset);
      if ($position === FALSE) {
        return FALSE;
      }
      $offset = $position + mb_strlen($word);
    }

    return TRUE;
  }

  /**
   * Checks that the icon id contains every query word as a substring.
   *
   * @param string $id_lower
   *   The lowercased full icon id.
   * @param string[] $query_words
   *   The lowercased query words.
   *
   * @return bool
   *   TRUE when all query words are substrings of the icon id.
   */
  private function containsAllWords(string $id_lower, array $query_words): bool {
    if ($query_words === []) {
      return FALSE;
    }

    foreach ($query_words as $word) {
      if (!str_contains($id_lower, $word)) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
