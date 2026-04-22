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
    private CacheBackendInterface $cache,
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
   * @param callable|null $result_callback
   *   Optional callback to format results.
   *
   * @return array
   *   Array of matching icon results.
   */
  public function search(
    string $query,
    array $allowed_icon_pack = [],
    int $max_result = self::SEARCH_RESULT,
    ?callable $result_callback = NULL,
  ): array {
    $query = trim($query);

    // Return empty if query is too short.
    if (strlen($query) < self::SEARCH_MIN_LENGTH) {
      return [];
    }

    // Limit max results.
    $max_result = min($max_result, self::SEARCH_RESULT_MAX);

    // Generate cache key based on search parameters.
    $cache_key = 'icon_search:' . md5($query . implode(':', $allowed_icon_pack) . $max_result);

    // Try to get cached results.
    if ($cached = $this->cache->get($cache_key)) {
      $results = $cached->data;
    }
    else {
      // Perform the search.
      $results = $this->doSearch($query, $allowed_icon_pack, $max_result);
      // Cache for 1 hour.
      $this->cache->set($cache_key, $results, time() + 3600);
    }

    // Apply result callback if provided.
    if ($result_callback !== NULL) {
      $formatted_results = [];
      foreach ($results as $icon_full_id) {
        if ($formatted_result = $result_callback($icon_full_id)) {
          $formatted_results[] = $formatted_result;
        }
      }
      return $formatted_results;
    }

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
    $count = 0;

    // Get all icons from allowed packs.
    $icons = $this->pluginManagerIconPack->getIcons($allowed_icon_pack);

    foreach ($icons as $icon_full_id => $icon_data) {
      if ($count >= $max_result) {
        break;
      }

      $icon_id_lower = mb_strtolower($icon_full_id);

      // Check if query matches the icon ID.
      // Priority 1: Exact match.
      if ($icon_id_lower === $query_lower) {
        array_unshift($results, $icon_full_id);
        $count++;
        continue;
      }

      // Priority 2: Starts with query.
      if (str_starts_with($icon_id_lower, $query_lower)) {
        $results[] = $icon_full_id;
        $count++;
        continue;
      }

      // Priority 3: Contains query.
      if (str_contains($icon_id_lower, $query_lower)) {
        $results[] = $icon_full_id;
        $count++;
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
        $count++;
      }
    }

    return $results;
  }

  /**
   * Create a result entry for list format.
   *
   * @param string $icon_full_id
   *   The full icon ID (pack_id:icon_id).
   *
   * @return array|null
   *   The formatted result entry or NULL if icon not found.
   */
  public static function createResultEntry(string $icon_full_id): ?array {
    $icon_pack_manager = \Drupal::service('plugin.manager.icon_pack');
    $icon = $icon_pack_manager->getIcon($icon_full_id);

    if (!$icon instanceof IconDefinitionInterface) {
      return NULL;
    }

    return [
      'value' => $icon_full_id,
      'label' => $icon_full_id,
    ];
  }

  /**
   * Create a result entry for grid format with icon preview.
   *
   * @param string $icon_full_id
   *   The full icon ID (pack_id:icon_id).
   *
   * @return array|null
   *   The formatted result entry with preview or NULL if icon not found.
   */
  public static function createResultEntryGrid(string $icon_full_id): ?array {
    $icon_pack_manager = \Drupal::service('plugin.manager.icon_pack');
    $renderer = \Drupal::service('renderer');
    $icon = $icon_pack_manager->getIcon($icon_full_id);

    if (!$icon instanceof IconDefinitionInterface) {
      return NULL;
    }

    // Build icon render array.
    $icon_render = [
      '#type' => 'icon',
      '#pack_id' => $icon->getPackId(),
      '#icon_id' => $icon->getIconId(),
      '#settings' => [
        'width' => self::ICON_PREVIEW_SIZE,
        'height' => self::ICON_PREVIEW_SIZE,
      ],
    ];

    // Render the icon.
    $icon_html = $renderer->renderRoot($icon_render);

    return [
      'value' => $icon_full_id,
      'label' => $icon_full_id,
      'preview' => (string) $icon_html,
    ];
  }

}
