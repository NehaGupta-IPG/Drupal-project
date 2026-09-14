<?php

declare(strict_types=1);

namespace Drupal\Core\Theme\Icon;

/**
 * Interface for icon search services.
 *
 * @internal
 *   This API is experimental.
 */
interface IconSearchInterface {

  /**
   * Minimum search query length.
   */
  public const int SEARCH_MIN_LENGTH = 2;

  /**
   * Default maximum number of search results.
   */
  public const int SEARCH_RESULT = 50;

  /**
   * Upper bound for results served to untrusted requests.
   *
   * The service itself honors any positive $max_result. Code exposing the
   * search over HTTP, such as the core autocomplete route, is expected to
   * clamp the requested amount to this value.
   */
  public const int SEARCH_RESULT_MAX = 200;

  /**
   * Searches for icons matching the query.
   *
   * @param string $query
   *   The search query string.
   * @param array $allowed_icon_pack
   *   Array of allowed icon pack IDs to limit the search.
   * @param int $max_result
   *   Maximum number of results to return.
   *
   * @return string[]
   *   Full icon ids, best match first.
   */
  public function search(
    string $query,
    array $allowed_icon_pack = [],
    int $max_result = self::SEARCH_RESULT,
  ): array;

}
