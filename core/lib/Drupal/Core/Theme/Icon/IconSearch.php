<?php

declare(strict_types=1);

namespace Drupal\Core\Theme\Icon;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;

/**
 * Service for searching icons.
 *
 * @internal
 *   This API is experimental.
 */
class IconSearch {

  /**
   * Minimum search query length.
   */
  public const int SEARCH_MIN_LENGTH = 2;

  /**
   * Default maximum number of search results.
   */
  public const int SEARCH_RESULT = 50;

  /**
   * Maximum allowed search results.
   */
  public const int SEARCH_RESULT_MAX = 200;

  /**
   * Icon preview size in pixels.
   */
  public const int ICON_PREVIEW_SIZE = 32;

  /**
   * Constructs an IconSearch object.
   *
   * @param \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface $pluginManagerIconPack
   *   The icon pack manager service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(
    private readonly IconPackManagerInterface $pluginManagerIconPack,
    private readonly CacheBackendInterface $cache,
  ) {}

  /**
   * Search for icons matching the query.
   *
   * @param string $query
   *   The search query string.
   * @param array $allowed_icon_pack
   *   Array of allowed icon pack IDs to limit search.
   * @param int $max_result
   *   Maximum number of results to return.
   *
   * @return array
   *   Array of matching icon IDs.
   */
  public function search(
    string $query,
    array $allowed_icon_pack = [],
    int $max_result = self::SEARCH_RESULT,
  ): array {
    $query = trim($query);

    // Return empty if query is too short.
    if (strlen($query) < self::SEARCH_MIN_LENGTH) {
      return [];
    }

    // Limit max results.
    $max_result = min($max_result, self::SEARCH_RESULT_MAX);

    // Generate a human-readable cache key based on search parameters.
    $cache_key = 'icon_search:' . $query . ':' . implode('+', $allowed_icon_pack) . ':' . $max_result;

    // Try to get cached results.
    if ($cached = $this->cache->get($cache_key)) {
      return $cached->data;
    }

    // Perform the search and cache permanently, tagged so results are
    // invalidated when icon packs change.
    $results = $this->doSearch($query, $allowed_icon_pack, $max_result);
    $this->cache->set($cache_key, $results, CacheBackendInterface::CACHE_PERMANENT, ['icon_pack_plugin']);

    return $results;
  }

  /**
   * Perform the actual icon search.
   *
   * @param string $query
   *   The search query string.
   * @param array $allowed_icon_pack
   *   Array of allowed icon pack IDs.
   * @param int $max_result
   *   Maximum number of results.
   *
   * @return array
   *   Array of matching icon IDs.
   */
  private function doSearch(string $query, array $allowed_icon_pack, int $max_result): array {
    $query_lower = mb_strtolower($query);
    $results = [];

    // Get all icons from allowed packs.
    $icons = $this->pluginManagerIconPack->getIcons($allowed_icon_pack);

    foreach ($icons as $icon_full_id => $icon_data) {
      if (\count($results) >= $max_result) {
        break;
      }

      $icon_id_lower = mb_strtolower($icon_full_id);

      // Priority 1: Exact match — prepend to put it first.
      if ($icon_id_lower === $query_lower) {
        array_unshift($results, $icon_full_id);
        continue;
      }

      // Priority 2: Starts with query.
      if (str_starts_with($icon_id_lower, $query_lower)) {
        $results[] = $icon_full_id;
        continue;
      }

      // Priority 3: Contains query.
      if (str_contains($icon_id_lower, $query_lower)) {
        $results[] = $icon_full_id;
        continue;
      }

      // Priority 4: Fuzzy match (each word in query appears in icon ID).
      $query_words = explode(' ', $query_lower);
      $all_words_match = TRUE;
      foreach ($query_words as $word) {
        if (!str_contains($icon_id_lower, $word)) {
          $all_words_match = FALSE;
          break;
        }
      }
      if ($all_words_match) {
        $results[] = $icon_full_id;
      }
    }

    return $results;
  }

}
