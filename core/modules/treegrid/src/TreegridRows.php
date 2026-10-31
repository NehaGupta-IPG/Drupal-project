<?php

declare(strict_types=1);

namespace Drupal\treegrid;

/**
 * Builds the response a treegrid rows endpoint returns.
 *
 * A consumer's children route returns the result of build() and sets the route
 * options _htmx_route, no_cache and _no_big_pipe, so core's htmx renderer sends
 * the rows with their attached assets.
 *
 * @api
 */
final class TreegridRows {

  /**
   * Builds a rows response: the rows in treegrid's wrapper, with its headers.
   *
   * @param array $rows
   *   One render array per row, through the consumer's row template.
   * @param int|null $total
   *   The level's total number of rows. Without it the response is the whole
   *   level.
   * @param string|null $next_id
   *   The id of the first row after this page. Without it the response reaches
   *   the end of the level.
   */
  public static function build(array $rows, ?int $total = NULL, ?string $next_id = NULL): array {
    $build = [
      '#theme' => 'treegrid_rows',
      '#rows' => $rows,
      '#cache' => ['max-age' => 0],
    ];
    $build['#attached']['http_header'][] = ['X-Treegrid-Rows', '1'];
    if ($total !== NULL) {
      $build['#attached']['http_header'][] = ['X-Treegrid-Total', (string) $total];
    }
    if ($next_id !== NULL) {
      $build['#attached']['http_header'][] = ['X-Treegrid-Next-Id', rawurlencode($next_id)];
    }
    return $build;
  }

}
